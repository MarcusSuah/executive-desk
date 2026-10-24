<?php

use App\Enums\TaskStatus;
use App\Models\CalendarEvent;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->executive = User::factory()->executive()->create();
    $this->task = Task::factory()->for($this->executive, 'assignee')->create();
});

/**
 * Build a queued status change as the browser sends it.
 *
 * @return array<string, mixed>
 */
function offlineMutation(Task $task, string $status, ?string $id = null, ?string $expectedUpdatedAt = null): array
{
    return [
        'client_mutation_id' => $id ?? (string) Str::uuid(),
        'type' => 'task.status',
        'task_id' => $task->id,
        'status' => $status,
        'expected_updated_at' => $expectedUpdatedAt ?? $task->updated_at->toIso8601ZuluString(),
    ];
}

it('returns a snapshot without agendas, notes, or attendees', function () {
    CalendarEvent::factory()->for($this->executive, 'owner')->create(['agenda' => 'Confidential agenda', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);

    $response = $this->actingAs($this->executive)->getJson(route('offline.snapshot'))->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and(array_keys($response->json('events.0')))->toBe(['id', 'title', 'starts_at', 'ends_at', 'all_day', 'location', 'status'])
        ->and($response->getContent())->not->toContain('Confidential agenda');
});

it('applies a queued change once even when the upload is retried', function () {
    $mutation = offlineMutation($this->task, 'completed');

    $first = $this->actingAs($this->executive)->postJson(route('offline.sync'), ['mutations' => [$mutation]]);
    $second = $this->actingAs($this->executive)->postJson(route('offline.sync'), ['mutations' => [$mutation]]);

    expect($first->json('results.0.result'))->toBe('applied')
        ->and($second->json('results.0.result'))->toBe('duplicate')
        ->and($this->task->refresh()->status)->toBe(TaskStatus::Completed);
});

it('reports a conflict when the task changed after the device last saw it', function () {
    $mutation = offlineMutation($this->task, 'completed', expectedUpdatedAt: $this->task->updated_at->subHour()->toIso8601ZuluString());

    $response = $this->actingAs($this->executive)->postJson(route('offline.sync'), ['mutations' => [$mutation]]);

    expect($response->json('results.0.result'))->toBe('conflict')
        ->and($this->task->refresh()->status)->toBe(TaskStatus::Pending);
});

it('rejects changes to tasks the user may not update', function () {
    $other = Task::factory()->create();

    $response = $this->actingAs($this->executive)->postJson(route('offline.sync'), ['mutations' => [offlineMutation($other, 'completed')]]);

    expect($response->json('results.0.result'))->toBe('rejected')
        ->and($other->refresh()->status)->toBe(TaskStatus::Pending);
});
