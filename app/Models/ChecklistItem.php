<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ChecklistItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $checklistable_type
 * @property int $checklistable_id
 * @property string $title
 * @property bool $is_completed
 * @property CarbonImmutable|null $completed_at
 * @property int|null $completed_by
 * @property int $position
 * @property-read CalendarEvent|Task|Presentation|null $checklistable Null when the parent was deleted.
 */
#[Fillable(['title', 'position'])]
class ChecklistItem extends Model
{
    /** @use HasFactory<ChecklistItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
            'position' => 'integer',
        ];
    }

    /**
     * Get the record the checklist belongs to.
     *
     * @return MorphTo<Model, $this>
     */
    public function checklistable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who completed the item.
     *
     * @return BelongsTo<User, $this>
     */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
