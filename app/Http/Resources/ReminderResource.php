<?php

namespace App\Http\Resources;

use App\Models\Reminder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Reminder
 */
class ReminderResource extends JsonResource
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
            'kind' => $this->kind->value,
            'channel' => $this->channel->value,
            'channel_label' => $this->channel->label(),
            'minutes_before' => $this->minutes_before,
            'scheduled_for' => $this->scheduled_for->toIso8601ZuluString(),
            'status' => $this->status->value,
            'status_reason' => $this->status_reason,
            'attempts' => $this->attempts,
            'sent_at' => $this->sent_at?->toIso8601ZuluString(),
            'last_error' => $this->last_error,
            'recipient' => $this->whenLoaded('user', fn () => $this->user->name),
            'can_toggle' => $request->user()?->can('toggle', $this->resource) ?? false,
        ];
    }
}
