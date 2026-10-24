<?php

namespace App\Models;

use App\Enums\BriefingStatus;
use App\Enums\BriefingType;
use Carbon\CarbonImmutable;
use Database\Factories\ExecutiveBriefingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Briefings are written by the briefing generator only.
 *
 * @property int $id
 * @property int $user_id
 * @property BriefingType $type
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property string $timezone
 * @property array<string, mixed> $content
 * @property CarbonImmutable $generated_at
 * @property BriefingStatus $status
 * @property CarbonImmutable|null $delivered_at
 * @property-read User $user
 */
class ExecutiveBriefing extends Model
{
    /** @use HasFactory<ExecutiveBriefingFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BriefingType::class,
            'status' => BriefingStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'content' => 'array',
            'generated_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * Get the user the briefing was prepared for.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the delivery attempts for the briefing.
     *
     * @return MorphMany<NotificationDelivery, $this>
     */
    public function deliveries(): MorphMany
    {
        return $this->morphMany(NotificationDelivery::class, 'deliverable');
    }
}
