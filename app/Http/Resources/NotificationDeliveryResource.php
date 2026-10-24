<?php

namespace App\Http\Resources;

use App\Models\NotificationDelivery;
use App\Models\Reminder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationDelivery
 */
class NotificationDeliveryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $reminder = $this->deliverable instanceof Reminder ? $this->deliverable : null;

        return [
            'id' => $this->id,
            'notification_type' => $this->notification_type,
            'recipient' => $this->whenLoaded('user', fn () => $this->user?->name),
            'channel' => $this->channel->value,
            'channel_label' => $this->channel->label(),
            'status' => $this->status->value,
            'attempt_number' => $this->attempt_number,
            'scheduled_for' => $this->scheduled_for?->toIso8601ZuluString(),
            'attempted_at' => $this->attempted_at->toIso8601ZuluString(),
            'provider_message_id' => $this->provider_message_id,
            'error_message' => $this->error_message,
            'reminder_id' => $reminder?->id,
            'reminder_status' => $reminder?->status->value,
            'can_retry' => $reminder !== null && ($request->user()?->can('retry', $reminder) ?? false),
        ];
    }
}
