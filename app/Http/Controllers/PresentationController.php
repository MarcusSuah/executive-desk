<?php

namespace App\Http\Controllers;

use App\Enums\PresentationStatus;
use App\Http\Requests\Presentations\StorePresentationRequest;
use App\Http\Requests\Presentations\UpdatePresentationRequest;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\PresentationResource;
use App\Models\CalendarEvent;
use App\Models\Presentation;
use App\Services\Presentations\PresentationManager;
use App\Support\FormOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PresentationController extends Controller
{
    public function __construct(private PresentationManager $presentations) {}

    /**
     * List presentations.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(PresentationStatus::class)],
            'scope' => ['nullable', Rule::in(['upcoming', 'past', 'all'])],
        ]);

        $scope = $filters['scope'] ?? 'upcoming';

        $presentations = Presentation::query()
            ->visibleTo($request->user())
            ->with(['owner:id,name,email,timezone', 'presenter:id,name,email,timezone'])
            ->withCount('attachments')
            ->when($filters['q'] ?? null, fn (Builder $query, $search) => $query->where('title', 'like', "%{$search}%"))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->when($scope === 'upcoming', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereNull('presentation_at')->orWhere('presentation_at', '>=', now()->startOfDay())))
            ->when($scope === 'past', fn (Builder $query) => $query->where('presentation_at', '<', now()->startOfDay()))
            ->orderByRaw('COALESCE(preparation_deadline_at, presentation_at) IS NULL')
            ->orderByRaw('COALESCE(preparation_deadline_at, presentation_at)')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('presentations/index', [
            'presentations' => PresentationResource::collection($presentations),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? '',
                'scope' => $scope,
            ],
            'statuses' => PresentationStatus::options(),
            'canCreate' => $request->user()->can('create', Presentation::class),
        ]);
    }

    /**
     * Show the form for creating a presentation.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Presentation::class);

        return Inertia::render('presentations/form', [
            'presentation' => null,
            'prefill' => $request->validate(['event' => ['nullable', 'integer']]),
            'options' => $this->formOptions($request),
        ]);
    }

    /**
     * Store a new presentation.
     */
    public function store(StorePresentationRequest $request): RedirectResponse
    {
        $presentation = $this->presentations->create(
            $request->presentationAttributes(),
            $request->checklist(),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Presentation created.')]);

        return to_route('presentations.show', $presentation);
    }

    /**
     * Display a presentation.
     */
    public function show(Request $request, Presentation $presentation): Response
    {
        Gate::authorize('view', $presentation);

        $user = $request->user();

        $presentation->load(['owner', 'presenter', 'approver', 'event', 'checklistItems', 'attachments.uploader']);

        return Inertia::render('presentations/show', [
            'presentation' => new PresentationResource($presentation),
            'history' => AuditLogResource::collection($presentation->auditLogs()->with('user:id,name')->limit(50)->get()),
            'can' => [
                'update' => $user->can('update', $presentation),
                'delete' => $user->can('delete', $presentation),
                'approve' => $user->can('approve', $presentation),
                'contribute' => $user->can('contribute', $presentation),
            ],
        ]);
    }

    /**
     * Show the form for editing a presentation.
     */
    public function edit(Request $request, Presentation $presentation): Response
    {
        Gate::authorize('update', $presentation);

        return Inertia::render('presentations/form', [
            'presentation' => new PresentationResource($presentation),
            'prefill' => [],
            'options' => $this->formOptions($request),
        ]);
    }

    /**
     * Update a presentation.
     */
    public function update(UpdatePresentationRequest $request, Presentation $presentation): RedirectResponse
    {
        $this->presentations->update($presentation, $request->presentationAttributes(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Presentation updated.')]);

        return to_route('presentations.show', $presentation);
    }

    /**
     * Approve a presentation.
     */
    public function approve(Request $request, Presentation $presentation): RedirectResponse
    {
        Gate::authorize('approve', $presentation);

        $this->presentations->approve($presentation, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Presentation approved.')]);

        return to_route('presentations.show', $presentation);
    }

    /**
     * Delete a presentation.
     */
    public function destroy(Request $request, Presentation $presentation): RedirectResponse
    {
        Gate::authorize('delete', $presentation);

        $this->presentations->delete($presentation, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Presentation deleted.')]);

        return to_route('presentations.index');
    }

    /**
     * Get the option lists for the presentation form.
     *
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        return [
            'executives' => FormOptions::executives(),
            'users' => FormOptions::users(),
            'statuses' => array_values(array_filter(
                PresentationStatus::options(),
                fn (array $option) => $option['value'] !== PresentationStatus::Approved->value,
            )),
            'timezones' => FormOptions::timezones(),
            'events' => CalendarEvent::query()
                ->visibleTo($request->user())
                ->active()
                ->where('starts_at', '>=', now()->subDays(7))
                ->orderBy('starts_at')
                ->limit(100)
                ->get(['id', 'title', 'starts_at'])
                ->map(fn (CalendarEvent $event) => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'starts_at' => $event->starts_at->toIso8601ZuluString(),
                ]),
        ];
    }
}
