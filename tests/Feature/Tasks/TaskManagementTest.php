<?php

use App\Enums\ReminderStatus;
use App\Enums\TaskStatus;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\RecurringTaskGenerator;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-26 01:00:00', 'UTC'));
    $this->assistant = User::factory()->assistant()->create();
    $this->executive = User::factory()->executive()->create();
});

/**
 * Build a valid task payload.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function taskPayload(User $assignee, array $overrides = []): array
{
    return [
        'title' => 'Review budget proposal',
        'assigned_to' => $assignee->id,
        'priority' => 'urgent',
        'timezone' => 'Asia/Manila',
        'due_at' => '2026-10-28T17:00',
        'estimated_minutes' => 45,
        'checklist' => ['Read summary', 'Check totals'],
        ...$overrides,
    ];
}

describe('creating and assigning tasks', function () {
    it('creates a task assigned to the executive with deadline reminders', function () {
        $this->actingAs($this->assistant)
            ->post(route('tasks.store'), taskPayload($this->executive))
            ->assertSessionHasNoErrors();

        $task = Task::sole();

        expect($task->assigned_to)->toBe($this->executive->id)
            ->and($task->created_by)->toBe($this->assistant->id)
            ->and($task->due_at->toDateTimeString())->toBe('2026-10-28 09:00:00')
            ->and($task->checklistItems()->count())->toBe(2)
            ->and(Reminder::query()->whereMorphedTo('remindable', $task)->pluck('minutes_before')->sort()->values()->all())->toBe([60, 1440]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'task.assigned', 'auditable_id' => $task->id]);
    });

    it('requires a due date for recurring tasks', function () {
        $this->actingAs($this->assistant)
            ->post(route('tasks.store'), taskPayload($this->executive, ['due_at' => null, 'recurrence_frequency' => 'daily']))
            ->assertSessionHasErrors('due_at');
    });

    it('forbids executives from creating tasks', function () {
        $this->actingAs($this->executive)
            ->post(route('tasks.store'), taskPayload($this->executive))
            ->assertForbidden();
    });

    it('records a reassignment in the task history', function () {
        $task = Task::factory()->for($this->executive, 'assignee')->create();
        $other = User::factory()->executive()->create();

        $this->actingAs($this->assistant)
            ->put(route('tasks.update', $task), taskPayload($other))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audit_logs', ['action' => 'task.reassigned', 'auditable_id' => $task->id]);
    });
});

describe('progress updates', function () {
    it('lets the assignee complete their task and cancels its reminders', function () {
        $this->actingAs($this->assistant)->post(route('tasks.store'), taskPayload($this->executive));
        $task = Task::sole();

        $this->actingAs($this->executive)
            ->patch(route('tasks.status', $task), ['status' => 'completed'])
            ->assertRedirect();

        expect($task->refresh()->status)->toBe(TaskStatus::Completed)
            ->and($task->completed_by)->toBe($this->executive->id)
            ->and($task->completed_at)->not->toBeNull()
            ->and(Reminder::where('status', ReminderStatus::Pending->value)->count())->toBe(0);
    });

    it('clears completion details when a task is reopened', function () {
        $task = Task::factory()->for($this->executive, 'assignee')->completed()->create();

        $this->actingAs($this->executive)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

        expect($task->refresh()->completed_at)->toBeNull();
        $this->assertDatabaseHas('audit_logs', ['action' => 'task.reopened', 'auditable_id' => $task->id]);
    });

    it('forbids an executive from updating a task assigned to someone else', function () {
        $task = Task::factory()->create();

        $this->actingAs($this->executive)
            ->patch(route('tasks.status', $task), ['status' => 'completed'])
            ->assertForbidden();
    });

    it('forbids an executive from editing task details', function () {
        $task = Task::factory()->for($this->executive, 'assignee')->create();

        $this->actingAs($this->executive)
            ->put(route('tasks.update', $task), taskPayload($this->executive))
            ->assertForbidden();
    });
});

describe('listing tasks', function () {
    it('shows an executive only their own tasks with overdue counts', function () {
        Task::factory()->for($this->executive, 'assignee')->overdue()->create();
        Task::factory()->for($this->executive, 'assignee')->create();
        Task::factory()->create();

        $this->actingAs($this->executive)
            ->get(route('tasks.index'))
            ->assertInertia(fn ($page) => $page
                ->where('counts.all', 2)
                ->where('counts.overdue', 1)
                ->has('tasks.data', 2));
    });

    it('forbids viewing a task that belongs to someone else', function () {
        $this->actingAs($this->executive)->get(route('tasks.show', Task::factory()->create()))->assertForbidden();
    });
});

describe('recurring tasks', function () {
    it('generates occurrences up to the horizon without duplicates', function () {
        $this->actingAs($this->assistant)->post(route('tasks.store'), taskPayload($this->executive, [
            'due_at' => '2026-10-26T17:00',
            'recurrence_frequency' => 'daily',
            'recurrence_interval' => 1,
        ]));
        $root = Task::sole();
        $generator = app(RecurringTaskGenerator::class);

        $first = $generator->generateFor($root);
        $second = $generator->generateFor($root);

        expect($first)->toBe(7)
            ->and($second)->toBe(0)
            ->and($root->occurrences()->count())->toBe(7)
            ->and($root->occurrences()->orderBy('occurrence_date')->first()->due_at->toDateTimeString())->toBe('2026-10-27 09:00:00');
    });

    it('keeps the local due time across a daylight-saving change', function () {
        $this->actingAs($this->assistant)->post(route('tasks.store'), taskPayload($this->executive, [
            'timezone' => 'America/New_York',
            'due_at' => '2026-10-30T09:00',
            'recurrence_frequency' => 'weekly',
            'recurrence_interval' => 1,
        ]));

        app(RecurringTaskGenerator::class)->generateFor(Task::sole(), CarbonImmutable::parse('2026-11-01 12:00:00', 'UTC'));

        $next = Task::query()->whereNotNull('recurrence_parent_id')->sole();

        expect($next->occurrence_date->toDateString())->toBe('2026-11-06')
            ->and($next->due_at->setTimezone('America/New_York')->format('H:i'))->toBe('09:00')
            ->and($next->due_at->toDateTimeString())->toBe('2026-11-06 14:00:00');
    });

    it('stops generating after the recurrence end date', function () {
        $this->actingAs($this->assistant)->post(route('tasks.store'), taskPayload($this->executive, [
            'due_at' => '2026-10-26T17:00',
            'recurrence_frequency' => 'daily',
            'recurrence_ends_on' => '2026-10-28',
        ]));

        expect(app(RecurringTaskGenerator::class)->generateFor(Task::sole()))->toBe(2);
    });
});
