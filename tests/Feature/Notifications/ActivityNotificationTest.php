<?php

use App\Models\CalendarEvent;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-26 00:00:00', 'UTC'));
    $this->assistant = User::factory()->assistant()->create(['name' => 'Sam Assistant']);
    $this->executive = User::factory()->executive()->create(['timezone' => 'Asia/Manila']);
});

it('tells the executive, but not the assistant who did it, about a rescheduled appointment', function () {
    $event = CalendarEvent::factory()->for($this->executive, 'owner')->create(['title' => 'Board Meeting', 'created_by' => $this->assistant->id]);

    $this->actingAs($this->assistant)->put(route('events.update', $event), [
        'owner_id' => $this->executive->id,
        'title' => 'Board Meeting',
        'type' => 'internal_meeting',
        'priority' => 'normal',
        'timezone' => 'Asia/Manila',
        'starts_at' => '2026-11-03T14:00',
        'ends_at' => '2026-11-03T15:00',
        'attendees' => [],
        'version' => 1,
    ])->assertSessionHasNoErrors();

    $notice = $this->executive->notifications()->sole();

    expect($notice->data['title'])->toBe('Sam Assistant rescheduled “Board Meeting”')
        ->and($notice->data['body'])->toContain('2:00 PM')
        ->and($notice->data['url'])->toBe("/events/{$event->id}")
        ->and($this->assistant->notifications()->count())->toBe(0);
});

it('tells the assistant who created a task when the executive completes it', function () {
    $task = Task::factory()->for($this->executive, 'assignee')->create(['title' => 'Sign contract', 'created_by' => $this->assistant->id]);

    $this->actingAs($this->executive)->patch(route('tasks.status', $task), ['status' => 'completed']);

    expect($this->assistant->notifications()->sole()->data['title'])->toBe("{$this->executive->name} completed the task “Sign contract”")
        ->and($this->executive->notifications()->count())->toBe(0);
});

it('tells everyone involved when a checklist item or file changes', function () {
    $event = CalendarEvent::factory()->for($this->executive, 'owner')->create(['created_by' => $this->assistant->id]);

    $this->actingAs($this->executive)->post(route('events.checklist.store', $event), ['title' => 'Print the board pack']);

    expect($this->assistant->notifications()->sole()->data['body'])->toBe('Item: Print the board pack');
});

it('sends a single notice to the assignee of a new task', function () {
    $this->actingAs($this->assistant)->post(route('tasks.store'), [
        'title' => 'Review budget',
        'assigned_to' => $this->executive->id,
        'priority' => 'high',
        'timezone' => 'UTC',
    ]);

    expect($this->executive->notifications()->sole()->data['title'])->toBe('New task: Review budget');
});

it('tells the affected user and other administrators about account changes', function () {
    $admin = User::factory()->administrator()->create(['name' => 'Avery Admin']);
    $otherAdmin = User::factory()->administrator()->create();

    $this->actingAs($admin)->put(route('admin.users.update', $this->executive), [
        'name' => $this->executive->name,
        'email' => $this->executive->email,
        'timezone' => 'Asia/Manila',
        'roles' => ['executive', 'assistant'],
    ]);

    expect($this->executive->notifications()->where('data->title', 'Avery Admin changed the roles of your account')->exists())->toBeTrue()
        ->and($otherAdmin->notifications()->where('data->title', "Avery Admin changed the roles of {$this->executive->name}")->exists())->toBeTrue()
        ->and($admin->notifications()->count())->toBe(0);
});

it('does not announce page views or sign-ins', function () {
    $this->actingAs($this->executive)->get(route('calendar'));
    $this->post(route('logout'));
    $this->post(route('login.store'), ['email' => $this->executive->email, 'password' => 'password']);

    expect(User::query()->get()->sum(fn (User $user) => $user->notifications()->count()))->toBe(0);
});

it('lists every notification on the notifications page', function () {
    $task = Task::factory()->for($this->executive, 'assignee')->create(['created_by' => $this->assistant->id]);
    $this->actingAs($this->executive)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

    $this->actingAs($this->assistant)
        ->get(route('inbox.index'))
        ->assertInertia(fn ($page) => $page->component('inbox/index')->has('notifications.data', 1)->where('unread', 1));
});
