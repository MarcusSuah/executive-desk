<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority->value,
            'status' => $this->status->value,
            'project' => $this->project,
            'starts_at' => $this->starts_at?->toIso8601ZuluString(),
            'due_at' => $this->due_at?->toIso8601ZuluString(),
            'timezone' => $this->timezone,
            'estimated_minutes' => $this->estimated_minutes,
            'completed_at' => $this->completed_at?->toIso8601ZuluString(),
            'notes' => $this->notes,
            'is_overdue' => $this->isOverdue(),
            'recurrence_frequency' => $this->recurrence_frequency?->value,
            'recurrence_interval' => $this->recurrence_interval,
            'recurrence_ends_on' => $this->recurrence_ends_on?->toDateString(),
            'recurrence_parent_id' => $this->recurrence_parent_id,
            'occurrence_date' => $this->occurrence_date?->toDateString(),
            'calendar_event_id' => $this->calendar_event_id,
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
            'assignee' => new UserSummaryResource($this->whenLoaded('assignee')),
            'creator' => new UserSummaryResource($this->whenLoaded('creator')),
            'completer' => new UserSummaryResource($this->whenLoaded('completer')),
            'event' => $this->whenLoaded('event', fn () => $this->event === null ? null : [
                'id' => $this->event->id,
                'title' => $this->event->title,
                'starts_at' => $this->event->starts_at->toIso8601ZuluString(),
            ]),
            'checklist' => ChecklistItemResource::collection($this->whenLoaded('checklistItems')),
            'checklist_total' => $this->whenCounted('checklistItems'),
            'checklist_completed' => $this->whenCounted('completedChecklistItems'),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
