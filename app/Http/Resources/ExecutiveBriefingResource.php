<?php

namespace App\Http\Resources;

use App\Models\ExecutiveBriefing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExecutiveBriefing
 */
class ExecutiveBriefingResource extends JsonResource
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
            'type' => $this->type->value,
            'period_start' => $this->period_start->toDateString(),
            'period_end' => $this->period_end->toDateString(),
            'timezone' => $this->timezone,
            'status' => $this->status->value,
            'generated_at' => $this->generated_at->toIso8601ZuluString(),
            'delivered_at' => $this->delivered_at?->toIso8601ZuluString(),
            'user' => new UserSummaryResource($this->whenLoaded('user')),
            'content' => $this->content,
        ];
    }
}
