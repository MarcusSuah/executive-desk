<?php

use App\Enums\EventStatus;
use App\Enums\ReminderStatus;
use App\Jobs\SendScheduleChangeNotice;
use App\Models\CalendarEvent;
use App\Models\Reminder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;

/**
 * Build a valid appointment payload for the given executive.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function eventPayload(User $owner, array $overrides = []): array
{
    return [
        'owner_id' => $owner->id,
        'title' => 'Board Meeting',
        'type' => 'internal_meeting',
        'priority' => 'high',
        'timezone' => 'Asia/Manila',
        'starts_at' => '2026-11-02T10:00',
        'ends_at' => '2026-11-02T11:00',
        'location' => 'Conference Room A',
        'agenda' => 'Quarterly review',
        'attendees' => [],
        'checklist' => ['Print the report'],
        ...$overrides,
    ];
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-26 00:00:00', 'UTC'));
    $this->assistant = User::factory()->assistant()->create();
    $this->executive = User::factory()->executive()->create(['timezone' => 'Asia/Manila']);
});

describe('creating appointments', function () {
    it('stores the start and end in UTC using the selected time zone', function () {
        $response = $this->actingAs($this->assistant)->post(route('events.store'), eventPayload($this->executive));

        $event = CalendarEvent::sole();
        $response->assertRedirect(route('events.show', $event));

        expect($event->starts_at->toDateTimeString())->toBe('2026-11-02 02:00:00')
            ->and($event->ends_at->toDateTimeString())->toBe('2026-11-02 03:00:00')
            ->and($event->timezone)->toBe('Asia/Manila')
            ->and($event->created_by)->toBe($this->assistant->id)
            ->and($event->checklistItems()->pluck('title')->all())->toBe(['Print the report']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'event.created', 'auditable_id' => $event->id, 'user_id' => $this->assistant->id]);
    });

    it('generates the default 60, 30, 15 and 5 minute email reminders for the owner', function () {
        $this->actingAs($this->assistant)->post(route('events.store'), eventPayload($this->executive));

        $reminders = Reminder::query()->where('user_id', $this->executive->id)->orderByDesc('minutes_before')->get();

        expect($reminders->pluck('minutes_before')->all())->toBe([60, 30, 15, 5])
            ->and($reminders->pluck('channel')->map->value->unique()->all())->toBe(['mail'])
            ->and($reminders->first()->scheduled_for->toDateTimeString())->toBe('2026-11-02 01:00:00');
    });

    it('rejects an end time that is not after the start time', function () {
        $this->actingAs($this->assistant)
            ->post(route('events.store'), eventPayload($this->executive, ['ends_at' => '2026-11-02T10:00']))
            ->assertSessionHasErrors(['ends_at' => 'The end time must be after the start time.']);

        expect(CalendarEvent::count())->toBe(0);
    });

    it('rejects a local time that falls in a daylight-saving gap', function () {
        $this->actingAs($this->assistant)
            ->post(route('events.store'), eventPayload($this->executive, [
                'timezone' => 'America/New_York',
                'starts_at' => '2027-03-14T02:30',
                'ends_at' => '2027-03-14T03:30',
            ]))
            ->assertSessionHasErrors('starts_at');
    });

    it('converts times across a daylight-saving change using the event time zone', function () {
        $this->actingAs($this->assistant)->post(route('events.store'), eventPayload($this->executive, [
            'timezone' => 'America/New_York',
            'starts_at' => '2026-11-02T09:00',
            'ends_at' => '2026-11-02T10:00',
        ]));

        // New York switches from EDT (UTC-4) to EST (UTC-5) on 1 November 2026.
        expect(CalendarEvent::sole()->starts_at->toDateTimeString())->toBe('2026-11-02 14:00:00');
    });

    it('requires the owner to be an executive', function () {
        $this->actingAs($this->assistant)
            ->post(route('events.store'), eventPayload($this->assistant))
            ->assertSessionHasErrors('owner_id');
    });

    it('rejects an empty payload with errors for every required field', function () {
        $this->actingAs($this->assistant)
            ->post(route('events.store'), [])
            ->assertSessionHasErrors(['owner_id', 'title', 'type', 'priority', 'timezone', 'starts_at', 'ends_at']);
    });

    it('forbids executives from creating appointments', function () {
        $this->actingAs($this->executive)
            ->post(route('events.store'), eventPayload($this->executive))
            ->assertForbidden();
    });

    it('redirects guests to the login page', function () {
        $this->post(route('events.store'), eventPayload($this->executive))->assertRedirect(route('login'));
    });
});

describe('conflict detection', function () {
    beforeEach(function () {
        CalendarEvent::factory()->for($this->executive, 'owner')->create([
            'title' => 'Existing Review',
            'starts_at' => '2026-11-02 02:30:00',
            'ends_at' => '2026-11-02 03:30:00',
        ]);
    });

    it('warns about an overlapping appointment instead of saving it', function () {
        $this->actingAs($this->assistant)
            ->post(route('events.store'), eventPayload($this->executive))
            ->assertSessionHasErrors('conflicts');

        expect(CalendarEvent::count())->toBe(1);
    });

    it('saves an overlapping appointment once the conflict is acknowledged', function () {
        $this->actingAs($this->assistant)
            ->post(route('events.store'), eventPayload($this->executive, ['acknowledge_conflicts' => true]))
            ->assertSessionHasNoErrors();

        expect(CalendarEvent::where('title', 'Board Meeting')->sole()->conflict_acknowledged)->toBeTrue();
    });

    it('allows an appointment that starts exactly when another ends', function () {
        $this->actingAs($this->assistant)
            ->post(route('events.store'), eventPayload($this->executive, ['starts_at' => '2026-11-02T11:30', 'ends_at' => '2026-11-02T12:00']))
            ->assertSessionHasNoErrors();
    });

    it('ignores cancelled appointments and other executives calendars', function () {
        CalendarEvent::query()->update(['status' => EventStatus::Cancelled->value]);
        CalendarEvent::factory()->create(['starts_at' => '2026-11-02 02:00:00', 'ends_at' => '2026-11-02 03:00:00']);

        $this->actingAs($this->assistant)
            ->post(route('events.store'), eventPayload($this->executive))
            ->assertSessionHasNoErrors();
    });
});

describe('rescheduling appointments', function () {
    beforeEach(function () {
        $this->actingAs($this->assistant)->post(route('events.store'), eventPayload($this->executive));
        $this->event = CalendarEvent::sole();
    });

    it('regenerates reminders for the new time and cancels the old ones', function () {
        Queue::fake([SendScheduleChangeNotice::class]);

        $this->actingAs($this->assistant)
            ->put(route('events.update', $this->event), eventPayload($this->executive, [
                'starts_at' => '2026-11-03T14:00',
                'ends_at' => '2026-11-03T15:00',
                'version' => 1,
            ]))
            ->assertSessionHasNoErrors();

        $pending = Reminder::query()->where('status', ReminderStatus::Pending->value)->get();

        expect($pending)->toHaveCount(4)
            ->and($pending->pluck('scheduled_for')->map->toDateTimeString()->sort()->first())->toBe('2026-11-03 05:00:00')
            ->and(Reminder::where('status', ReminderStatus::Cancelled->value)->count())->toBe(4)
            ->and($this->event->refresh()->version)->toBe(2);

        $this->assertDatabaseHas('audit_logs', ['action' => 'event.rescheduled', 'auditable_id' => $this->event->id]);
        Queue::assertPushed(SendScheduleChangeNotice::class, fn ($job) => $job->userId === $this->executive->id && $job->change === 'rescheduled');
    });

    it('does not create duplicate reminders when saved twice without changes', function () {
        foreach ([1, 2] as $version) {
            $this->actingAs($this->assistant)
                ->put(route('events.update', $this->event), eventPayload($this->executive, ['version' => $version]))
                ->assertSessionHasNoErrors();
        }

        expect(Reminder::count())->toBe(4)
            ->and(Reminder::where('status', ReminderStatus::Pending->value)->count())->toBe(4);
    });

    it('rejects a stale form when someone else changed the appointment', function () {
        $this->event->forceFill(['version' => 2])->save();

        $this->actingAs($this->assistant)
            ->put(route('events.update', $this->event), eventPayload($this->executive, ['title' => 'Stale', 'version' => 1]))
            ->assertSessionHasErrors('version');

        expect($this->event->refresh()->title)->toBe('Board Meeting');
    });
});

describe('cancelling appointments', function () {
    it('cancels the appointment and its pending reminders', function () {
        $this->actingAs($this->assistant)->post(route('events.store'), eventPayload($this->executive));
        $event = CalendarEvent::sole();

        $this->actingAs($this->assistant)
            ->post(route('events.cancel', $event), ['reason' => 'Chair unavailable'])
            ->assertRedirect(route('events.show', $event));

        expect($event->refresh()->status)->toBe(EventStatus::Cancelled)
            ->and($event->cancellation_reason)->toBe('Chair unavailable')
            ->and(Reminder::where('status', ReminderStatus::Pending->value)->count())->toBe(0);
    });

    it('forbids executives from cancelling appointments', function () {
        $event = CalendarEvent::factory()->for($this->executive, 'owner')->create();

        $this->actingAs($this->executive)->post(route('events.cancel', $event))->assertForbidden();
    });
});

describe('viewing appointments', function () {
    it('shows an executive their own appointment', function () {
        $event = CalendarEvent::factory()->for($this->executive, 'owner')->create();

        $this->actingAs($this->executive)->get(route('events.show', $event))->assertOk();
    });

    it('forbids an executive from viewing another executive appointment', function () {
        $event = CalendarEvent::factory()->create();

        $this->actingAs($this->executive)->get(route('events.show', $event))->assertForbidden();
    });

    it('lists only visible appointments on the calendar', function () {
        CalendarEvent::factory()->for($this->executive, 'owner')->create(['title' => 'Mine', 'starts_at' => '2026-10-27 02:00:00', 'ends_at' => '2026-10-27 03:00:00']);
        CalendarEvent::factory()->create(['title' => 'Someone else', 'starts_at' => '2026-10-27 02:00:00', 'ends_at' => '2026-10-27 03:00:00']);

        $this->actingAs($this->executive)
            ->get(route('calendar', ['view' => 'week', 'date' => '2026-10-27']))
            ->assertInertia(fn ($page) => $page
                ->component('calendar/index')
                ->has('events.data', 1)
                ->where('events.data.0.title', 'Mine')
                ->has('days', 7));
    });
});
