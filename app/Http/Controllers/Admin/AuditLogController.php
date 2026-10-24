<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Support\FormOptions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * List audit log entries.
     */
    public function index(Request $request): Response
    {
        // Areas come from what has actually been recorded, e.g. "event" from "event.created".
        $areas = AuditLog::query()
            ->selectRaw("DISTINCT SUBSTRING_INDEX(action, '.', 1) as area")
            ->orderBy('area')
            ->pluck('area')
            ->all();

        $filters = $request->validate([
            'area' => ['nullable', Rule::in($areas)],
            'user' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $timezone = $request->user()->timezone;

        $entries = AuditLog::query()
            ->with(['user:id,name', 'auditable'])
            ->when($filters['area'] ?? null, fn (Builder $query, $area) => $query->where('action', 'like', $area.'.%'))
            ->when($filters['user'] ?? null, fn (Builder $query, $userId) => $query->where('user_id', $userId))
            ->when($filters['from'] ?? null, fn (Builder $query, $from) => $query->where('created_at', '>=', CarbonImmutable::parse($from, $timezone)->startOfDay()->utc()))
            ->when($filters['to'] ?? null, fn (Builder $query, $to) => $query->where('created_at', '<', CarbonImmutable::parse($to, $timezone)->addDay()->startOfDay()->utc()))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('admin/audit-logs', [
            'entries' => AuditLogResource::collection($entries),
            'filters' => [
                'area' => $filters['area'] ?? '',
                'user' => isset($filters['user']) ? (string) $filters['user'] : '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
            'areas' => array_map(fn (string $area) => ['value' => $area, 'label' => ucwords(str_replace('_', ' ', $area))], $areas),
            'users' => FormOptions::users(),
        ]);
    }
}
