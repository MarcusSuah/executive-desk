<?php

use App\Enums\ReminderStatus;
use App\Mail\DigestMail;
use App\Mail\NoticeMail;
use App\Models\CalendarEvent;
use App\Models\DigestItem;
use App\Models\Reminder;
use App\Models\User;
use App\Services\Notifications\DigestScheduler;
use App\Services\Reminders\ReminderDeliverer;
use App\Services\Reminders\ReminderPlanner;
use App\Support\NotificationCategories;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00:00', 'UTC'));
    $this->assistant = User::factory()->assistant()->create(['name' => 'Sam Assistant']);
    $this->executive = User::factory()->executive()->create(['timezone' => 'UTC']);
});

/**
 * Change one category's settings for the user, keeping the defaults elsewhere.
 *
 * @param  array<string, bool|string>  $settings
 * @param  array<string, mixed>  $preferences
 */
function tuneCategory(User $user, string $category, array $settings, array $preferences = []): void
{
    $categories = NotificationCategories::defaults();
    $categories[$category] = [...$categories[$category], ...$settings];

    $user->preferences()->update(['categories' => $categories, ...$preferences]);
    $user->unsetRelation('notificationPreference');
}

/**
 * Have the assistant assign a new task to the executive.
 */
function assignTask(User $assistant, User $executive): void
{
    test()->actingAs($assistant)->post(route('tasks.store'), [
        'title' => 'Review budget',
        'assigned_to' => $executive->id,
        'priority' => 'high',
        'timezone' => 'UTC',
    ])->assertSessionHasNoErrors();
}

/**
 * Plan an appointment at 10:00 and deliver its one-hour email reminder at 09:00.
 */
function deliverHourBeforeReminder(User $executive): Reminder
{
    $event = CalendarEvent::factory()->for($executive, 'owner')->create(['starts_at' => '2026-10-26 10:00:00', 'ends_at' => '2026-10-26 11:00:00']);
    app(ReminderPlanner::class)->syncEvent($event);
    test()->travelTo(CarbonImmutable::parse('2026-10-26 09:00:00', 'UTC'));

    $reminder = Reminder::query()->whereMorphedTo('remindable', $event)->where('minutes_before', 60)->sole();
    $reminder->forceFill(['status' => ReminderStatus::Queued])->save();
    app(ReminderDeliverer::class)->deliver($reminder->id, true);

    return $reminder->refresh();
}

describe('channels per category', function () {
    it('plans no email reminders when meeting reminders are turned off for email', function () {
        tuneCategory($this->executive, 'meeting_reminders', ['mail' => false]);

        $event = CalendarEvent::factory()->for($this->executive, 'owner')->create(['starts_at' => '2026-10-26 10:00:00', 'ends_at' => '2026-10-26 11:00:00']);
        app(ReminderPlanner::class)->syncEvent($event);

        expect(Reminder::query()->whereMorphedTo('remindable', $event)->count())->toBe(0);
    });

    it('emails a task assignment, and leaves it out of the bell when in-app is off for it', function () {
        Mail::fake();
        tuneCategory($this->executive, 'task_assignments', ['in_app' => false]);

        assignTask($this->assistant, $this->executive);

        Mail::assertSent(NoticeMail::class, fn (NoticeMail $mail) => $mail->hasTo($this->executive->email) && $mail->subjectLine === 'New task: Review budget');
        expect($this->executive->notifications()->count())->toBe(0);
    });

    it('sends nothing for activity when in-app and email are both turned off', function () {
        Mail::fake();
        $this->executive->preferences()->update(['in_app_enabled' => false, 'email_enabled' => false]);

        assignTask($this->assistant, $this->executive);

        Mail::assertNothingSent();
        expect($this->executive->notifications()->count())->toBe(0);
    });

    it('keeps the old briefing and schedule-change switches for preferences saved before categories', function () {
        $this->executive->preferences()->update(['daily_briefing_enabled' => false, 'schedule_change_notifications' => false]);
        $preferences = $this->executive->refresh()->preferences();

        expect($preferences->allows('daily_briefing', 'in_app'))->toBeFalse()
            ->and($preferences->allows('daily_briefing', 'mail'))->toBeFalse()
            ->and($preferences->allows('weekly_briefing', 'mail'))->toBeTrue()
            ->and($preferences->allows('schedule_changes', 'mail'))->toBeFalse()
            ->and($preferences->allows('schedule_changes', 'in_app'))->toBeTrue();
    });
});

describe('quiet hours', function () {
    it('holds back a medium priority reminder during quiet hours but still adds it to the bell', function () {
        Mail::fake();
        tuneCategory($this->executive, 'meeting_reminders', ['priority' => 'medium'], [
            'quiet_hours_enabled' => true, 'quiet_hours_start' => '22:00:00', 'quiet_hours_end' => '09:30:00',
        ]);

        $reminder = deliverHourBeforeReminder($this->executive);

        Mail::assertNothingSent();
        expect($reminder->status)->toBe(ReminderStatus::Skipped)
            ->and($this->executive->notifications()->count())->toBe(1);
    });

    it('lets a high priority reminder through quiet hours when allowed', function () {
        Mail::fake();
        $this->executive->preferences()->update([
            'quiet_hours_enabled' => true, 'quiet_hours_start' => '22:00:00', 'quiet_hours_end' => '09:30:00', 'quiet_hours_allow_high' => true,
        ]);

        expect(deliverHourBeforeReminder($this->executive)->status)->toBe(ReminderStatus::Sent);
        Mail::assertSent(NoticeMail::class, 1);
    });

    it('holds back a high priority reminder when high priority may not break through', function () {
        Mail::fake();
        $this->executive->preferences()->update([
            'quiet_hours_enabled' => true, 'quiet_hours_start' => '08:30:00', 'quiet_hours_end' => '17:00:00', 'quiet_hours_allow_high' => false,
        ]);

        expect(deliverHourBeforeReminder($this->executive)->status)->toBe(ReminderStatus::Skipped);
        Mail::assertNothingSent();
    });
});

describe('digest', function () {
    beforeEach(function () {
        $this->executive->preferences()->update(['digest_frequency' => 'daily', 'digest_time' => '09:00:00']);
    });

    it('keeps a medium priority email for the digest and sends the digest once at the chosen time', function () {
        Mail::fake();

        assignTask($this->assistant, $this->executive);

        Mail::assertNothingSent();
        expect(DigestItem::query()->where('user_id', $this->executive->id)->sole()->title)->toBe('New task: Review budget')
            ->and($this->executive->notifications()->count())->toBe(1)
            ->and(app(DigestScheduler::class)->run())->toBe(0);

        $this->travelTo(CarbonImmutable::parse('2026-10-26 09:02:00', 'UTC'));

        expect(app(DigestScheduler::class)->run())->toBe(1)
            ->and(app(DigestScheduler::class)->run())->toBe(0)
            ->and(DigestItem::query()->whereNull('digested_at')->count())->toBe(0);

        Mail::assertSent(DigestMail::class, fn (DigestMail $mail) => $mail->hasTo($this->executive->email)
            && $mail->subjectLine === 'Your digest: 1 update'
            && isset($mail->sections['Task assignments']));
    });

    it('still emails high priority alerts straight away', function () {
        Mail::fake();
        tuneCategory($this->executive, 'task_assignments', ['priority' => 'high']);

        assignTask($this->assistant, $this->executive);

        Mail::assertSent(NoticeMail::class, 1);
        expect(DigestItem::count())->toBe(0);
    });

    it('only gathers low priority alerts when asked to', function () {
        Mail::fake();
        $this->executive->preferences()->update(['digest_include' => 'low_only']);

        assignTask($this->assistant, $this->executive);

        Mail::assertSent(NoticeMail::class, 1);
        expect(DigestItem::count())->toBe(0);
    });
});
