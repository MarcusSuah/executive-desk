<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use App\Services\Reports\ReportExporter;
use App\Support\AuditCatalog;
use App\Support\DisplayTime;
use App\Support\FormOptions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AuditLogController extends Controller
{
    /**
     * The most entries one export may contain (newest first).
     */
    public const MAX_EXPORT_ROWS = 10000;

    /**
     * @var array<int, int>
     */
    private const PER_PAGE = [10, 25, 50, 100];

    /**
     * List audit log entries with today's statistics.
     */
    public function index(Request $request): Response
    {
        $modules = $this->modules();
        $filters = $this->filters($request, $modules);
        $timezone = $request->user()->timezone;

        $entries = $this->query($filters, $timezone)
            ->with(['user:id,name', 'auditable'])
            ->latest('id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return Inertia::render('admin/audit-logs', [
            'entries' => AuditLogResource::collection($entries),
            'stats' => $this->stats($timezone),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'module' => $filters['module'] ?? '',
                'user' => isset($filters['user']) ? (string) $filters['user'] : '',
                'result' => $filters['result'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
                'per_page' => $filters['per_page'],
            ],
            'modules' => array_map(fn (string $module) => ['value' => $module, 'label' => $module], $modules),
            'users' => FormOptions::users(),
            'perPageOptions' => self::PER_PAGE,
            'maxExportRows' => self::MAX_EXPORT_ROWS,
        ]);
    }

    /**
     * Export the filtered entries as CSV or Excel.
     */
    public function export(Request $request, ReportExporter $exporter, AuditLogger $audit): HttpResponse
    {
        $format = $request->validate(['format' => ['required', Rule::in(['csv', 'xlsx'])]])['format'];
        $filters = $this->filters($request, $this->modules());
        $user = $request->user();

        $entries = $this->query($filters, $user->timezone)
            ->with(['user:id,name', 'auditable'])
            ->latest('id')
            ->limit(self::MAX_EXPORT_ROWS)
            ->get();

        $rows = $entries->map(fn (AuditLog $entry) => [
            'when' => DisplayTime::forUser(CarbonImmutable::instance($entry->created_at), $user),
            'user' => $entry->user->name ?? 'System',
            'action' => Str::ucfirst(str_replace(['.', '_'], ' ', $entry->action)),
            'module' => AuditCatalog::module($entry->action),
            'record' => $this->record($entry),
            'result' => Str::ucfirst(AuditCatalog::result($entry->action, $entry->new_values)),
            'ip' => (string) $entry->ip_address,
        ])->all();

        $audit->log('export.audit_logs', null, [], [
            'format' => $format,
            'filters' => array_filter(array_diff_key($filters, ['per_page' => true])),
            'rows' => count($rows),
        ], $user);

        return $exporter->export([
            'title' => 'Activity log',
            'columns' => [
                'when' => 'Date & time',
                'user' => 'User',
                'action' => 'Action',
                'module' => 'Module',
                'record' => 'Record',
                'result' => 'Result',
                'ip' => 'IP address',
            ],
            'rows' => $rows,
            'summary' => ['Events' => count($rows)],
        ], $format, 'Exported '.DisplayTime::forUser(now(), $user), $user->timezone);
    }

    /**
     * Get the modules that have recorded entries.
     *
     * @return array<int, string>
     */
    private function modules(): array
    {
        return AuditLog::query()
            ->selectRaw("DISTINCT SUBSTRING_INDEX(action, '.', 1) as area")
            ->pluck('area')
            ->map(fn (string $area) => AuditCatalog::module($area.'.'))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Validate the filters.
     *
     * @param  array<int, string>  $modules
     * @return array{q?: string, module?: string, user?: int, result?: string, from?: string, to?: string, per_page: int}
     */
    private function filters(Request $request, array $modules): array
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'module' => ['nullable', Rule::in($modules)],
            'user' => ['nullable', 'integer'],
            'result' => ['nullable', Rule::in(AuditCatalog::RESULTS)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', Rule::in(self::PER_PAGE)],
        ]);

        $normalized = ['per_page' => (int) ($filters['per_page'] ?? 10)];

        foreach (['q', 'module', 'result', 'from', 'to'] as $key) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $normalized[$key] = (string) $filters[$key];
            }
        }

        if (isset($filters['user'])) {
            $normalized['user'] = (int) $filters['user'];
        }

        return $normalized;
    }

    /**
     * Build the filtered query.
     *
     * @param  array{q?: string, module?: string, user?: int, result?: string, from?: string, to?: string, per_page: int}  $filters
     * @return Builder<AuditLog>
     */
    private function query(array $filters, string $timezone): Builder
    {
        return AuditLog::query()
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

                $query->where(fn (Builder $query) => $query
                    ->where('action', 'like', $like)
                    ->orWhere('ip_address', 'like', $like)
                    ->orWhereHas('user', fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('email', 'like', $like)));
            })
            ->when($filters['module'] ?? null, fn (Builder $query, string $module) => $query->where(function (Builder $query) use ($module): void {
                foreach (AuditCatalog::prefixesFor($module) ?: [Str::snake($module)] as $prefix) {
                    $query->orWhere('action', 'like', $prefix.'.%');
                }
            }))
            ->when($filters['user'] ?? null, fn (Builder $query, int $userId) => $query->where('user_id', $userId))
            ->when($filters['result'] ?? null, fn (Builder $query, string $result) => $query->whereRaw('('.AuditCatalog::RESULT_SQL.') = ?', [...AuditCatalog::resultBindings(), $result]))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', CarbonImmutable::parse($from, $timezone)->startOfDay()->utc()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<', CarbonImmutable::parse($to, $timezone)->addDay()->startOfDay()->utc()));
    }

    /**
     * Count today's events (in the viewer's time zone).
     *
     * @return array{total_today: int, failed_today: int, most_active_module: string|null, most_active_count: int}
     */
    private function stats(string $timezone): array
    {
        $today = AuditLog::query()->where('created_at', '>=', now()->setTimezone($timezone)->startOfDay()->utc());

        $busiest = (clone $today)
            ->selectRaw("SUBSTRING_INDEX(action, '.', 1) as area, COUNT(*) as total")
            ->groupBy('area')
            ->get()
            ->groupBy(fn (AuditLog $row) => AuditCatalog::module($row->getAttribute('area').'.'))
            ->map(fn ($rows) => [
                'module' => AuditCatalog::module($rows->first()?->getAttribute('area').'.'),
                'total' => $rows->sum(fn (AuditLog $row) => (int) $row->getAttribute('total')),
            ])
            ->sortByDesc('total')
            ->first();

        return [
            'total_today' => (clone $today)->count(),
            'failed_today' => (clone $today)->whereRaw('('.AuditCatalog::RESULT_SQL.") = 'failed'", AuditCatalog::resultBindings())->count(),
            'most_active_module' => $busiest['module'] ?? null,
            'most_active_count' => $busiest['total'] ?? 0,
        ];
    }

    /**
     * Describe the affected record for the export.
     */
    private function record(AuditLog $entry): string
    {
        if ($entry->auditable_type === null) {
            return '';
        }

        $subject = $entry->auditable?->getAttribute('title') ?? $entry->auditable?->getAttribute('name');

        return Str::headline($entry->auditable_type)." #{$entry->auditable_id}".(is_string($subject) ? " – {$subject}" : '');
    }
}
