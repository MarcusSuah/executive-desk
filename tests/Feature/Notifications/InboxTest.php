<?php

use App\Enums\ReminderStatus;
use App\Models\CalendarEvent;
use App\Models\Reminder;
use App\Models\User;
use App\Services\Notifications\InAppNotifier;
use App\Services\Reminders\ReminderDeliverer;
use App\Services\Reminders\ReminderPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->executive = User::factory()->executive()->create();
});

it('shares the unread count and latest items with every page', function () {
    $notifier = app(InAppNotifier::class);
    $notifier->notify($this->executive, 'one', 'event_reminder', 'Board meeting in 15 minutes', 'Conference Room A', '/events/1');
    $notifier->notify($this->executive, 'two', 'task_assigned', 'New task: Review budget', 'Assigned by Sam', '/tasks/1');

    $this->actingAs($this->executive)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('inbox.unread', 2)
            ->has('inbox.items', 2)
            ->where('inbox.items.0.read', false));
});

it('creates one bell item per key even when called repeatedly', function () {
    $notifier = app(InAppNotifier::class);

    $notifier->notify($this->executive, 'same-key', 'briefing', 'Daily briefing', 'Summary');
    $notifier->notify($this->executive, 'same-key', 'briefing', 'Daily briefing', 'Summary');

    expect($this->executive->notifications()->count())->toBe(1);
});

it('marks an item read and opens its in-app link', function () {
    app(InAppNotifier::class)->notify($this->executive, 'k', 'task_assigned', 'New task', 'Body', 'https://evil.example/phish?x=1');
    $item = $this->executive->notifications()->sole();

    expect($item->data['url'])->toBe('/phish?x=1');

    $this->actingAs($this->executive)->post(route('inbox.read', $item->id))->assertRedirect('/phish?x=1');

    expect($item->refresh()->read_at)->not->toBeNull();
});

it('does not let users read someone else\'s notification', function () {
    $other = User::factory()->create();
    app(InAppNotifier::class)->notify($other, 'k', 'briefing', 'Private', 'Body');

    $this->actingAs($this->executive)
        ->post(route('inbox.read', $other->notifications()->sole()->id))
        ->assertNotFound();
});

it('marks every notification read', function () {
    app(InAppNotifier::class)->notify($this->executive, 'a', 'briefing', 'A', 'Body');
    app(InAppNotifier::class)->notify($this->executive, 'b', 'briefing', 'B', 'Body');

    $this->actingAs($this->executive)->post(route('inbox.read-all'))->assertRedirect();

    expect($this->executive->unreadNotifications()->count())->toBe(0);
});

it('adds a single bell item for a reminder delivered on several channels', function () {
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00:00', 'UTC'));
    $this->executive->preferences()->update(['push_enabled' => true]);
    $event = CalendarEvent::factory()->for($this->executive, 'owner')->create(['title' => 'Board meeting', 'starts_at' => '2026-10-26 10:00:00', 'ends_at' => '2026-10-26 11:00:00']);
    app(ReminderPlanner::class)->syncEvent($event);
    $this->travelTo(CarbonImmutable::parse('2026-10-26 09:00:00', 'UTC'));

    Reminder::query()->where('minutes_before', 60)->get()->each(function (Reminder $reminder) {
        $reminder->forceFill(['status' => ReminderStatus::Queued])->save();
        app(ReminderDeliverer::class)->deliver($reminder->id, true);
    });

    expect(Reminder::where('minutes_before', 60)->count())->toBe(2)
        ->and($this->executive->notifications()->count())->toBe(1)
        ->and($this->executive->notifications()->sole()->data['title'])->toContain('Board meeting');
});

it('tells an assignee about a new task in the bell', function () {
    Queue::fake();

    $this->actingAs(User::factory()->assistant()->create())->post(route('tasks.store'), [
        'title' => 'Review budget',
        'assigned_to' => $this->executive->id,
        'priority' => 'high',
        'timezone' => 'UTC',
    ])->assertSessionHasNoErrors();

    expect($this->executive->notifications()->sole()->data['title'])->toBe('New task: Review budget');
});
