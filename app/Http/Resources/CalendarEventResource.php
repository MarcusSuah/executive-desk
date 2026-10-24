<?php

namespace App\Http\Resources;

use App\Models\CalendarEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CalendarEvent
 */
class CalendarEventResource extends JsonResource
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
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'category' => $this->category,
            'priority' => $this->priority->value,
            'status' => $this->status->value,
            'starts_at' => $this->starts_at->toIso8601ZuluString(),
            'ends_at' => $this->ends_at->toIso8601ZuluString(),
            'timezone' => $this->timezone,
            'all_day' => $this->all_day,
            'location' => $this->location,
            'meeting_url' => $this->meeting_url,
            'agenda' => $this->agenda,
            'notes' => $this->notes,
            'preparation_notes' => $this->preparation_notes,
            'reminder_minutes' => $this->reminder_minutes,
            'conflict_acknowledged' => $this->conflict_acknowledged,
            'cancelled_at' => $this->cancelled_at?->toIso8601ZuluString(),
            'cancellation_reason' => $this->cancellation_reason,
            'version' => $this->version,
            'owner' => new UserSummaryResource($this->whenLoaded('owner')),
            'creator' => new UserSummaryResource($this->whenLoaded('creator')),
            'attendees' => EventAttendeeResource::collection($this->whenLoaded('attendees')),
            'checklist' => ChecklistItemResource::collection($this->whenLoaded('checklistItems')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            'presentations' => PresentationResource::collection($this->whenLoaded('presentations')),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
