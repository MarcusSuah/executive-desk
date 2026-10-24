<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Services\AuditLogger;
use App\Services\Reports\ReportBuilder;
use App\Services\Reports\ReportExporter;
use App\Support\FormOptions;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReportController extends Controller
{
    public function __construct(private ReportBuilder $reports) {}

    /**
     * Show a report.
     */
    public function index(Request $request): Response
    {
        [$report, $from, $to, $executiveId] = $this->parameters($request);
        $user = $request->user();

        return Inertia::render('reports/index', [
            'report' => $this->reports->build($report, $user, $from, $to, $executiveId),
            'filters' => [
                'report' => $report,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'executive' => $executiveId !== null ? (string) $executiveId : '',
            ],
            'reports' => ReportBuilder::availableTo($user),
            'executives' => $this->canFilterByExecutive($request) ? FormOptions::executives() : [],
        ]);
    }

    /**
     * Export a report as CSV, Excel, or PDF using the same filters and visibility rules.
     */
    public function export(Request $request, ReportExporter $exporter, AuditLogger $audit): HttpResponse
    {
        [$report, $from, $to, $executiveId] = $this->parameters($request);
        $format = $request->validate(['format' => ['required', Rule::in(['csv', 'xlsx', 'pdf'])]])['format'];
        $user = $request->user();

        $built = $this->reports->build($report, $user, $from, $to, $executiveId);

        $audit->log('export.report', null, [], [
            'report' => $report,
            'format' => $format,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'rows' => count($built['rows']),
        ], $user);

        return $exporter->export($built, $format, $from->format('M j, Y').' – '.$to->format('M j, Y'), $user->timezone);
    }

    /**
     * Validate and normalize the report parameters.
     *
     * @return array{0: string, 1: CarbonImmutable, 2: CarbonImmutable, 3: int|null}
     */
    private function parameters(Request $request): array
    {
        $user = $request->user();
        $available = Arr::pluck(ReportBuilder::availableTo($user), 'value');

        abort_if($available === [], 403);

        $validated = $request->validate([
            'report' => ['nullable', Rule::in($available)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'executive' => ['nullable', 'integer'],
        ]);

        $today = now($user->timezone)->startOfDay();
        $from = isset($validated['from']) ? CarbonImmutable::parse($validated['from'], $user->timezone) : $today->subDays(29);
        $to = isset($validated['to']) ? CarbonImmutable::parse($validated['to'], $user->timezone) : $today;

        abort_if($from->diffInDays($to) > 366, 422, 'Choose a date range of one year or less.');

        $executiveId = $this->canFilterByExecutive($request) && isset($validated['executive']) ? (int) $validated['executive'] : null;

        return [$validated['report'] ?? $available[0], $from, $to, $executiveId];
    }

    /**
     * Determine whether the user may narrow reports to one executive.
     */
    private function canFilterByExecutive(Request $request): bool
    {
        return $request->user()->can(Permission::ManageCalendar->value) || $request->user()->can(Permission::ManageTasks->value);
    }
}
