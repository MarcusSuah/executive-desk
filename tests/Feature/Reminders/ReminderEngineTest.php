<?php

use App\Enums\DeliveryStatus;
use App\Enums\NotificationChannel;
use App\Enums\ReminderStatus;
use App\Jobs\SendReminder;
use App\Mail\NoticeMail;
use App\Models\CalendarEvent;
use App\Models\NotificationDelivery;
use App\Models\PushSubscription;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;
use App\Services\Notifications\Push\PushGateway;
use App\Services\Notifications\Push\PushOutcome;
use App\Services\Reminders\ReminderDeliverer;
use App\Services\Reminders\ReminderDispatcher;
use App\Services\Reminders\ReminderPlanner;
use App\Services\Reminders\TransientDeliveryException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00:00', 'UTC'));
    $this->executive = User::factory()->executive()->create();
    $this->event = CalendarEvent::factory()->for($this->executive, 'owner')->create([
        'starts_at' => '2026-10-26 10:00:00',
        'ends_at' => '2026-10-26 11:00:00',
    ]);
    app(ReminderPlanner::class)->syncEvent($this->event);
});

/**
 * Get the event's reminder with the given lead time.
 */
function reminderFor(CalendarEvent $event, int $minutes): Reminder
{
    return Reminder::query()->whereMorphedTo('remindable', $event)->where('minutes_before', $minutes)->sole();
}

describe('planning', function () {
    it('does not create reminders whose time has already passed', function () {
        $this->travelTo(CarbonImmutable::parse('2026-10-26 09:40:00', 'UTC'));

        $event = CalendarEvent::factory()->for($this->executive, 'owner')->create([
            'starts_at' => '2026-10-26 10:00:00',
            'ends_at' => '2026-10-26 10:30:00',
        ]);
        app(ReminderPlanner::class)->syncEvent($event);

        expect(Reminder::query()->whereMorphedTo('remindable', $event)->pluck('minutes_before')->sort()->values()->all())->toBe([5, 15]);
    });

    it('creates one reminder per enabled channel', function () {
        $this->executive->preferences()->update(['push_enabled' => true]);
        app(ReminderPlanner::class)->syncEvent($this->event->refresh());

        expect(Reminder::query()->whereMorphedTo('remindable', $this->event)->where('status', 'pending')->count())->toBe(8);
    });

    it('skips SMS reminders for a recipient without a verified phone', function () {
        $this->executive->preferences()->update(['sms_enabled' => true]);
        app(ReminderPlanner::class)->syncEvent($this->event->refresh());

        expect(Reminder::where('channel', NotificationChannel::Sms->value)->count())->toBe(0);
    });

    it('uses the event specific intervals when they are set', function () {
        $this->event->update(['reminder_minutes' => [90]]);
        app(ReminderPlanner::class)->syncEvent($this->event);

        expect(Reminder::query()->whereMorphedTo('remindable', $this->event)->where('status', 'pending')->pluck('minutes_before')->all())->toBe([90]);
    });

    it('revives reminders when an appointment moves away and back', function () {
        $planner = app(ReminderPlanner::class);

        $this->event->update(['starts_at' => '2026-10-27 10:00:00', 'ends_at' => '2026-10-27 11:00:00']);
        $planner->syncEvent($this->event);
        $this->event->update(['starts_at' => '2026-10-26 10:00:00', 'ends_at' => '2026-10-26 11:00:00']);
        $planner->syncEvent($this->event);

        expect(Reminder::query()->whereMorphedTo('remindable', $this->event)->where('scheduled_for', '<', '2026-10-27 00:00:00')->where('status', 'pending')->count())->toBe(4);
    });

    it('cancels reminders when a task is completed', function () {
        $task = Task::factory()->for($this->executive, 'assignee')->create(['due_at' => '2026-10-28 09:00:00']);
        app(ReminderPlanner::class)->syncTask($task);

        $task->forceFill(['status' => 'completed'])->save();
        app(ReminderPlanner::class)->syncTask($task);

        expect(Reminder::query()->whereMorphedTo('remindable', $task)->pluck('status')->map->value->unique()->all())->toBe(['cancelled']);
    });
});

describe('dispatching', function () {
    it('queues due reminders exactly once even when run twice', function () {
        Queue::fake([SendReminder::class]);
        $this->travelTo(CarbonImmutable::parse('2026-10-26 09:00:00', 'UTC'));

        $dispatcher = app(ReminderDispatcher::class);
        $first = $dispatcher->dispatchDue();
        $second = $dispatcher->dispatchDue();

        expect($first)->toBe(1)->and($second)->toBe(0)
            ->and(reminderFor($this->event, 60)->status)->toBe(ReminderStatus::Queued);
        Queue::assertPushed(SendReminder::class, 1);
    });

    it('skips reminders whose delivery window has passed', function () {
        Queue::fake([SendReminder::class]);
        $this->travelTo(CarbonImmutable::parse('2026-10-26 09:45:00', 'UTC'));

        app(ReminderDispatcher::class)->run();

        expect(reminderFor($this->event, 60)->status)->toBe(ReminderStatus::Skipped)
            ->and(reminderFor($this->event, 30)->status)->toBe(ReminderStatus::Queued)
            ->and(reminderFor($this->event, 15)->status)->toBe(ReminderStatus::Queued);
    });

    it('fails a reminder stuck while processing instead of resending it', function () {
        $reminder = reminderFor($this->event, 60);
        $reminder->forceFill(['status' => ReminderStatus::Processing, 'reserved_at' => now()->subMinutes(20)])->save();

        app(ReminderDispatcher::class)->recoverStale();

        expect($reminder->refresh()->status)->toBe(ReminderStatus::Failed)
            ->and($reminder->last_error)->toContain('outcome is unknown');
    });

    it('re-dispatches a reminder whose queued job was lost', function () {
        Queue::fake([SendReminder::class]);
        $reminder = reminderFor($this->event, 60);
        $reminder->forceFill(['status' => ReminderStatus::Queued, 'reserved_at' => now()->subMinutes(90), 'attempts' => 1])->save();

        app(ReminderDispatcher::class)->recoverStale();

        Queue::assertPushed(SendReminder::class, fn (SendReminder $job) => $job->reminderId === $reminder->id);
    });
});

describe('delivery', function () {
    beforeEach(function () {
        $this->reminder = reminderFor($this->event, 60);
        $this->reminder->forceFill(['status' => ReminderStatus::Queued, 'reserved_at' => now()])->save();
        $this->travelTo(CarbonImmutable::parse('2026-10-26 09:00:00', 'UTC'));
    });

    it('emails the reminder and records the delivery', function () {
        Mail::fake();

        app(ReminderDeliverer::class)->deliver($this->reminder->id, false);

        Mail::assertSent(NoticeMail::class, fn (NoticeMail $mail) => $mail->hasTo($this->executive->email)
            && str_contains($mail->subjectLine, 'starts in 1 hour'));

        expect($this->reminder->refresh()->status)->toBe(ReminderStatus::Sent)
            ->and($this->reminder->attempts)->toBe(1)
            ->and($this->reminder->sent_at)->not->toBeNull();

        $this->assertDatabaseHas('notification_deliveries', [
            'deliverable_id' => $this->reminder->id,
            'channel' => 'mail',
            'status' => 'sent',
            'notification_type' => 'event_reminder',
        ]);
    });

    it('does nothing when another worker already claimed the reminder', function () {
        Mail::fake();
        $this->reminder->forceFill(['status' => ReminderStatus::Processing])->save();

        app(ReminderDeliverer::class)->deliver($this->reminder->id, false);

        Mail::assertNothingSent();
        expect(NotificationDelivery::count())->toBe(0);
    });

    it('skips a reminder whose appointment was rescheduled', function () {
        Mail::fake();
        $this->event->update(['starts_at' => '2026-10-26 12:00:00', 'ends_at' => '2026-10-26 13:00:00']);

        app(ReminderDeliverer::class)->deliver($this->reminder->id, false);

        Mail::assertNothingSent();
        expect($this->reminder->refresh()->status)->toBe(ReminderStatus::Skipped);
    });

    it('skips a reminder when the recipient turned the channel off', function () {
        Mail::fake();
        $this->executive->preferences()->update(['email_enabled' => false]);

        app(ReminderDeliverer::class)->deliver($this->reminder->id, false);

        Mail::assertNothingSent();
        expect($this->reminder->refresh()->status)->toBe(ReminderStatus::Skipped);
    });

    it('requeues a transient mail failure for a retry with backoff', function () {
        Mail::shouldReceive('to->send')->andThrow(new TransportException('Connection to smtp.example.com:587 timed out'));

        expect(fn () => app(ReminderDeliverer::class)->deliver($this->reminder->id, false))
            ->toThrow(TransientDeliveryException::class);

        expect($this->reminder->refresh()->status)->toBe(ReminderStatus::Queued)
            ->and($this->reminder->last_error)->not->toContain('smtp.example.com');

        $this->assertDatabaseHas('notification_deliveries', ['deliverable_id' => $this->reminder->id, 'status' => 'failed', 'attempt_number' => 1]);
        expect((new SendReminder($this->reminder->id))->backoff())->toBe([60, 300, 900]);
    });

    it('fails the reminder on the final attempt instead of retrying forever', function () {
        Mail::shouldReceive('to->send')->andThrow(new TransportException('Connection refused'));

        app(ReminderDeliverer::class)->deliver($this->reminder->id, true);

        expect($this->reminder->refresh()->status)->toBe(ReminderStatus::Failed);
    });

    it('reports SMS as unavailable when no provider is configured', function () {
        config(['services.sms.driver' => 'none']);
        $phoneUser = User::factory()->executive()->withVerifiedPhone()->create();
        $phoneUser->preferences()->update(['sms_enabled' => true, 'email_enabled' => false]);
        $event = CalendarEvent::factory()->for($phoneUser, 'owner')->create(['starts_at' => '2026-10-26 10:00:00', 'ends_at' => '2026-10-26 11:00:00']);
        $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00:00', 'UTC'));
        app(ReminderPlanner::class)->syncEvent($event);
        $reminder = reminderFor($event, 60);
        $reminder->forceFill(['status' => ReminderStatus::Queued])->save();
        $this->travelTo(CarbonImmutable::parse('2026-10-26 09:00:00', 'UTC'));

        app(ReminderDeliverer::class)->deliver($reminder->id, false);

        expect($reminder->refresh()->status)->toBe(ReminderStatus::Unavailable)
            ->and(NotificationDelivery::sole()->status)->toBe(DeliveryStatus::Unavailable);
    });

    it('removes an expired push subscription and reports push as unavailable', function () {
        $this->executive->preferences()->update(['push_enabled' => true, 'email_enabled' => false]);
        PushSubscription::factory()->for($this->executive)->create();
        $this->reminder->forceFill(['channel' => NotificationChannel::Push])->save();

        $gateway = Mockery::mock(PushGateway::class);
        $gateway->shouldReceive('isConfigured')->andReturnTrue();
        $gateway->shouldReceive('send')->once()->andReturn(PushOutcome::expired());
        $this->app->instance(PushGateway::class, $gateway);

        app(ReminderDeliverer::class)->deliver($this->reminder->id, false);

        expect(PushSubscription::count())->toBe(0)
            ->and($this->reminder->refresh()->status)->toBe(ReminderStatus::Unavailable);
    });
});

it('lets a recipient turn an individual reminder off', function () {
    $reminder = reminderFor($this->event, 5);

    $this->actingAs($this->executive)->patch(route('reminders.toggle', $reminder))->assertRedirect();

    expect($reminder->refresh()->status)->toBe(ReminderStatus::Disabled);
});

it('forbids another executive from turning a reminder off', function () {
    $reminder = reminderFor($this->event, 5);

    $this->actingAs(User::factory()->executive()->create())->patch(route('reminders.toggle', $reminder))->assertForbidden();
});
