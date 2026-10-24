<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
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
            'action' => $this->action,
            'actor' => $this->whenLoaded('user', fn () => $this->user?->name),
            'auditable_type' => $this->auditable_type,
            'auditable_id' => $this->auditable_id,
            'subject' => $this->whenLoaded('auditable', fn () => $this->auditable?->getAttribute('title') ?? $this->auditable?->getAttribute('name')),
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'ip_address' => $this->when($request->user()?->can('audit.view'), $this->ip_address),
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
