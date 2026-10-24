<?php

namespace App\Http\Controllers;

use App\Enums\AttendeeRole;
use App\Enums\EventType;
use App\Enums\Permission;
use App\Enums\Priority;
use App\Http\Requests\Calendar\StoreEventRequest;
use App\Http\Requests\Calendar\UpdateEventRequest;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\CalendarEventResource;
use App\Http\Resources\ReminderResource;
use App\Models\CalendarEvent;
use App\Services\Calendar\EventScheduler;
use App\Support\FormOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(private EventScheduler $scheduler) {}

    /**
     * Show the form for creating an appointment.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', CalendarEvent::class);

        $prefill = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'time' => ['nullable', 'date_format:H:i'],
            'executive' => ['nullable', 'integer'],
        ]);

        return Inertia::render('events/form', [
            'event' => null,
            'prefill' => $prefill,
            'options' => $this->formOptions(),
        ]);
    }

    /**
     * Store a newly created appointment.
     */
    public function store(StoreEventRequest $request): RedirectResponse
    {
        $event = $this->scheduler->create(
            $request->eventAttributes(),
            $request->attendees(),
            $request->checklist(),
            $request->user(),
            $request->acknowledgesConflicts(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Appointment scheduled.')]);

        return to_route('events.show', $event);
    }

    /**
     * Display an appointment.
     */
    public function show(Request $request, CalendarEvent $event): Response
    {
        Gate::authorize('view', $event);

        $user = $request->user();

        $event->load([
            'owner', 'creator', 'attendees', 'checklistItems', 'attachments.uploader',
            'tasks.assignee', 'presentations',
        ]);

        $reminders = $event->reminders()
            ->with('user:id,name')
            ->when(! $user->can(Permission::ManageCalendar->value), fn ($query) => $query->where('user_id', $user->id))
            ->orderBy('scheduled_for')
            ->limit(200)
            ->get();

        return Inertia::render('events/show', [
            'event' => new CalendarEventResource($event),
            'reminders' => ReminderResource::collection($reminders),
            'history' => AuditLogResource::collection($event->auditLogs()->with('user:id,name')->limit(50)->get()),
            'can' => [
                'update' => $user->can('update', $event),
                'cancel' => $user->can('cancel', $event),
                'delete' => $user->can('delete', $event),
                'updateChecklist' => $user->can('updateChecklist', $event),
                'attachFiles' => $user->can('attachFiles', $event),
                'complete' => $user->can('update', $event) && $event->ends_at->isPast(),
            ],
        ]);
    }

    /**
     * Show the form for editing an appointment.
     */
    public function edit(CalendarEvent $event): Response
    {
        Gate::authorize('update', $event);

        return Inertia::render('events/form', [
            'event' => new CalendarEventResource($event->load('attendees')),
            'prefill' => [],
            'options' => $this->formOptions(),
        ]);
    }

    /**
     * Update or reschedule an appointment.
     */
    public function update(UpdateEventRequest $request, CalendarEvent $event): RedirectResponse
    {
        $this->scheduler->update(
            $event,
            $request->eventAttributes(),
            $request->attendees(),
            $request->user(),
            $request->integer('version'),
            $request->acknowledgesConflicts(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Appointment updated.')]);

        return to_route('events.show', $event);
    }

    /**
     * Cancel an appointment.
     */
    public function cancel(Request $request, CalendarEvent $event): RedirectResponse
    {
        Gate::authorize('cancel', $event);

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $this->scheduler->cancel($event, $request->user(), $validated['reason'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Appointment cancelled.')]);

        return to_route('events.show', $event);
    }

    /**
     * Mark a past appointment as completed.
     */
    public function complete(Request $request, CalendarEvent $event): RedirectResponse
    {
        Gate::authorize('update', $event);

        abort_unless($event->ends_at->isPast(), 422, 'Only past appointments can be completed.');

        $this->scheduler->complete($event, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Appointment marked as completed.')]);

        return to_route('events.show', $event);
    }

    /**
     * Delete an appointment.
     */
    public function destroy(Request $request, CalendarEvent $event): RedirectResponse
    {
        Gate::authorize('delete', $event);

        $this->scheduler->delete($event, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Appointment deleted.')]);

        return to_route('calendar');
    }

    /**
     * Get the option lists for the appointment form.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'executives' => FormOptions::executives(),
            'users' => FormOptions::users(),
            'types' => EventType::options(),
            'priorities' => Priority::options(),
            'attendeeRoles' => AttendeeRole::options(),
            'timezones' => FormOptions::timezones(),
            'reminderMinutes' => FormOptions::reminderMinutes(),
        ];
    }
}
