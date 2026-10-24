<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Enums\Permission;
use App\Enums\Priority;
use Carbon\CarbonImmutable;
use Database\Factories\CalendarEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $owner_id
 * @property int|null $created_by
 * @property string $title
 * @property string|null $description
 * @property EventType $type
 * @property string|null $category
 * @property Priority $priority
 * @property EventStatus $status
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property string $timezone
 * @property bool $all_day
 * @property string|null $location
 * @property string|null $meeting_url
 * @property string|null $agenda
 * @property string|null $notes
 * @property string|null $preparation_notes
 * @property array<int, int>|null $reminder_minutes
 * @property bool $conflict_acknowledged
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property int $version
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $owner
 * @property-read User|null $creator
 */
#[Fillable([
    'owner_id', 'title', 'description', 'type', 'category', 'priority', 'starts_at', 'ends_at',
    'timezone', 'all_day', 'location', 'meeting_url', 'agenda', 'notes', 'preparation_notes',
    'reminder_minutes',
])]
class CalendarEvent extends Model
{
    /** @use HasFactory<CalendarEventFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'scheduled',
        'priority' => 'normal',
        'version' => 1,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'priority' => Priority::class,
            'status' => EventStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
            'reminder_minutes' => 'array',
            'conflict_acknowledged' => 'boolean',
            'cancelled_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    /**
     * Get the executive whose calendar holds the event.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the user who created the event.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the event attendees.
     *
     * @return HasMany<EventAttendee, $this>
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(EventAttendee::class);
    }

    /**
     * Get the tasks related to the event.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get the presentations scheduled for the event.
     *
     * @return HasMany<Presentation, $this>
     */
    public function presentations(): HasMany
    {
        return $this->hasMany(Presentation::class);
    }

    /**
     * Get the event preparation checklist.
     *
     * @return MorphMany<ChecklistItem, $this>
     */
    public function checklistItems(): MorphMany
    {
        return $this->morphMany(ChecklistItem::class, 'checklistable')->orderBy('position')->orderBy('id');
    }

    /**
     * Get the event attachments.
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest('id');
    }

    /**
     * Get the event reminders.
     *
     * @return MorphMany<Reminder, $this>
     */
    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }

    /**
     * Get the audit history of the event.
     *
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('id');
    }

    /**
     * Determine whether the event has been cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === EventStatus::Cancelled;
    }

    /**
     * Get the IDs of internal users who should be notified about the event.
     *
     * @return array<int, int>
     */
    public function recipientIds(): array
    {
        $attendeeIds = $this->relationLoaded('attendees')
            ? $this->attendees->pluck('user_id')
            : $this->attendees()->pluck('user_id');

        return $attendeeIds
            ->push($this->owner_id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Scope a query to events the user may view.
     *
     * @param  Builder<CalendarEvent>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can(Permission::ManageCalendar->value)) {
            return;
        }

        $query->where(function (Builder $query) use ($user): void {
            $query->where('owner_id', $user->id)
                ->orWhereHas('attendees', fn (Builder $query) => $query->where('user_id', $user->id));
        });
    }

    /**
     * Scope a query to events that are not cancelled.
     *
     * @param  Builder<CalendarEvent>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', '!=', EventStatus::Cancelled->value);
    }

    /**
     * Scope a query to events overlapping the given UTC interval.
     *
     * @param  Builder<CalendarEvent>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonImmutable $startsAt, CarbonImmutable $endsAt): void
    {
        $query->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt);
    }
}
