<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\CalendarEvent;
use App\Models\OfflineMutation;
use App\Models\Task;
use App\Services\Tasks\TaskManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OfflineSyncController extends Controller
{
    /**
     * Return the limited, non-sensitive data kept on the device for offline viewing:
     * upcoming appointment times, titles, and locations, and the user's open task titles.
     * Agendas, notes, attendees, and documents are deliberately excluded.
     */
    public function snapshot(Request $request): JsonResponse
    {
        $user = $request->user();

        $events = CalendarEvent::query()
            ->visibleTo($user)
            ->active()
            ->where('ends_at', '>=', now())
            ->where('starts_at', '<', now()->addDays(7))
            ->orderBy('starts_at')
            ->limit(100)
            ->get(['id', 'title', 'starts_at', 'ends_at', 'all_day', 'location', 'status'])
            ->map(fn (CalendarEvent $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'starts_at' => $event->starts_at->toIso8601ZuluString(),
                'ends_at' => $event->ends_at->toIso8601ZuluString(),
                'all_day' => $event->all_day,
                'location' => $event->location,
                'status' => $event->status->value,
            ]);

        $tasks = Task::query()
            ->where('assigned_to', $user->id)
            ->open()
            ->orderByRaw('due_at IS NULL')
            ->orderBy('due_at')
            ->limit(100)
            ->get(['id', 'title', 'due_at', 'priority', 'status', 'updated_at'])
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'due_at' => $task->due_at?->toIso8601ZuluString(),
                'priority' => $task->priority->value,
                'status' => $task->status->value,
                'updated_at' => $task->updated_at?->toIso8601ZuluString(),
            ]);

        return response()->json([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'timezone' => $user->timezone,
            'synced_at' => now()->toIso8601ZuluString(),
            'events' => $events,
            'tasks' => $tasks,
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * Apply task status changes queued while offline.
     *
     * Each change carries a client-generated ID so a retried upload is not applied
     * twice, and the task's updated_at as seen offline so a change made against stale
     * data is reported as a conflict instead of overwriting newer work.
     */
    public function sync(Request $request, TaskManager $tasks): JsonResponse
    {
        $validated = $request->validate([
            'mutations' => ['required', 'array', 'max:50'],
            'mutations.*.client_mutation_id' => ['required', 'uuid'],
            'mutations.*.type' => ['required', Rule::in(['task.status'])],
            'mutations.*.task_id' => ['required', 'integer'],
            'mutations.*.status' => ['required', Rule::enum(TaskStatus::class)],
            'mutations.*.expected_updated_at' => ['nullable', 'date'],
        ]);

        $results = [];

        foreach ($validated['mutations'] as $mutation) {
            $results[] = [
                'client_mutation_id' => $mutation['client_mutation_id'],
                ...$this->apply($request, $mutation, $tasks),
            ];
        }

        return response()->json(['results' => $results])->header('Cache-Control', 'private, no-store');
    }

    /**
     * Apply one queued change and record its outcome.
     *
     * @param  array{client_mutation_id: string, type: string, task_id: int, status: string, expected_updated_at?: string|null}  $mutation
     * @return array{result: string, message: string}
     */
    private function apply(Request $request, array $mutation, TaskManager $tasks): array
    {
        $user = $request->user();

        try {
            return DB::transaction(function () use ($user, $mutation, $tasks): array {
                $existing = OfflineMutation::query()
                    ->where('user_id', $user->id)
                    ->where('client_mutation_id', $mutation['client_mutation_id'])
                    ->first();

                if ($existing !== null) {
                    return ['result' => 'duplicate', 'message' => 'This change was already synchronized.'];
                }

                $task = Task::query()->lockForUpdate()->find($mutation['task_id']);

                $outcome = match (true) {
                    $task === null => ['result' => 'rejected', 'message' => 'The task no longer exists.'],
                    ! $user->can('updateProgress', $task) => ['result' => 'rejected', 'message' => 'You are not allowed to update this task.'],
                    $this->changedSince($task, $mutation['expected_updated_at'] ?? null) => [
                        'result' => 'conflict',
                        'message' => "“{$task->title}” was changed by someone else while you were offline. Review it and try again.",
                    ],
                    default => null,
                };

                if ($outcome === null) {
                    $tasks->changeStatus($task, TaskStatus::from($mutation['status']), $user);
                    $outcome = ['result' => 'applied', 'message' => "“{$task->title}” updated."];
                }

                OfflineMutation::create([
                    'user_id' => $user->id,
                    'client_mutation_id' => $mutation['client_mutation_id'],
                    'type' => $mutation['type'],
                    'result' => $outcome['result'],
                ]);

                return $outcome;
            });
        } catch (UniqueConstraintViolationException) {
            return ['result' => 'duplicate', 'message' => 'This change was already synchronized.'];
        }
    }

    /**
     * Determine whether the task changed after the version the device saw.
     */
    private function changedSince(Task $task, ?string $expectedUpdatedAt): bool
    {
        if ($expectedUpdatedAt === null || $task->updated_at === null) {
            return false;
        }

        return $task->updated_at->greaterThan(CarbonImmutable::parse($expectedUpdatedAt));
    }
}
