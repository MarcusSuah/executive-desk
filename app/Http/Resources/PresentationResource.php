<?php

namespace App\Http\Resources;

use App\Models\Presentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Presentation
 */
class PresentationResource extends JsonResource
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
            'presentation_at' => $this->presentation_at?->toIso8601ZuluString(),
            'timezone' => $this->timezone,
            'venue' => $this->venue,
            'meeting_url' => $this->meeting_url,
            'preparation_deadline_at' => $this->preparation_deadline_at?->toIso8601ZuluString(),
            'review_deadline_at' => $this->review_deadline_at?->toIso8601ZuluString(),
            'status' => $this->status->value,
            'approved_at' => $this->approved_at?->toIso8601ZuluString(),
            'notes' => $this->notes,
            'presenter_name' => $this->presenter_name,
            'calendar_event_id' => $this->calendar_event_id,
            'owner' => new UserSummaryResource($this->whenLoaded('owner')),
            'presenter' => new UserSummaryResource($this->whenLoaded('presenter')),
            'approver' => new UserSummaryResource($this->whenLoaded('approver')),
            'event' => $this->whenLoaded('event', fn () => $this->event === null ? null : [
                'id' => $this->event->id,
                'title' => $this->event->title,
                'starts_at' => $this->event->starts_at->toIso8601ZuluString(),
            ]),
            'checklist' => ChecklistItemResource::collection($this->whenLoaded('checklistItems')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'attachments_count' => $this->whenCounted('attachments'),
        ];
    }
}
