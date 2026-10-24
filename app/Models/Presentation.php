<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\PresentationStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PresentationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $owner_id
 * @property int|null $created_by
 * @property int|null $calendar_event_id
 * @property int|null $presenter_id
 * @property string|null $presenter_name
 * @property string $title
 * @property string|null $description
 * @property CarbonImmutable|null $presentation_at
 * @property string $timezone
 * @property string|null $venue
 * @property string|null $meeting_url
 * @property CarbonImmutable|null $preparation_deadline_at
 * @property CarbonImmutable|null $review_deadline_at
 * @property PresentationStatus $status
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $owner
 * @property-read User|null $presenter
 */
#[Fillable([
    'owner_id', 'calendar_event_id', 'presenter_id', 'presenter_name', 'title', 'description',
    'presentation_at', 'timezone', 'venue', 'meeting_url', 'preparation_deadline_at',
    'review_deadline_at', 'status', 'notes',
])]
class Presentation extends Model
{
    /** @use HasFactory<PresentationFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PresentationStatus::class,
            'presentation_at' => 'datetime',
            'preparation_deadline_at' => 'datetime',
            'review_deadline_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Get the executive the presentation belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the user who created the presentation.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the internal presenter.
     *
     * @return BelongsTo<User, $this>
     */
    public function presenter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'presenter_id');
    }

    /**
     * Get the user who approved the presentation.
     *
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the related appointment.
     *
     * @return BelongsTo<CalendarEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id');
    }

    /**
     * Get the presentation checklist.
     *
     * @return MorphMany<ChecklistItem, $this>
     */
    public function checklistItems(): MorphMany
    {
        return $this->morphMany(ChecklistItem::class, 'checklistable')->orderBy('position')->orderBy('id');
    }

    /**
     * Get the presentation files.
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest('id');
    }

    /**
     * Get the presentation reminders.
     *
     * @return MorphMany<Reminder, $this>
     */
    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }

    /**
     * Get the presentation history.
     *
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('id');
    }

    /**
     * Determine whether the presentation still needs preparation.
     */
    public function isActive(): bool
    {
        return in_array($this->status->value, PresentationStatus::active(), true);
    }

    /**
     * Get the IDs of internal users who should be reminded about the presentation.
     *
     * @return array<int, int>
     */
    public function recipientIds(): array
    {
        return array_values(array_unique(array_filter([$this->owner_id, $this->presenter_id])));
    }

    /**
     * Scope a query to presentations the user may view.
     *
     * @param  Builder<Presentation>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can(Permission::ManagePresentations->value)) {
            return;
        }

        $query->where(function (Builder $query) use ($user): void {
            $query->where('owner_id', $user->id)->orWhere('presenter_id', $user->id);
        });
    }
}
