<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Priority;
use App\Enums\RecurrenceFrequency;
use App\Enums\TaskStatus;
use Carbon\CarbonImmutable;
use Database\Factories\TaskFactory;
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
 * @property string $title
 * @property string|null $description
 * @property int|null $assigned_to
 * @property int|null $created_by
 * @property int|null $calendar_event_id
 * @property string|null $project
 * @property Priority $priority
 * @property TaskStatus $status
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $due_at
 * @property string $timezone
 * @property int|null $estimated_minutes
 * @property CarbonImmutable|null $completed_at
 * @property int|null $completed_by
 * @property string|null $notes
 * @property RecurrenceFrequency|null $recurrence_frequency
 * @property int|null $recurrence_interval
 * @property CarbonImmutable|null $recurrence_ends_on
 * @property int|null $recurrence_parent_id
 * @property CarbonImmutable|null $occurrence_date
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $assignee
 */
#[Fillable([
    'title', 'description', 'assigned_to', 'calendar_event_id', 'project', 'priority', 'starts_at',
    'due_at', 'timezone', 'estimated_minutes', 'notes', 'recurrence_frequency', 'recurrence_interval',
    'recurrence_ends_on',
])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'priority' => 'normal',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'status' => TaskStatus::class,
            'starts_at' => 'datetime',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'estimated_minutes' => 'integer',
            'recurrence_frequency' => RecurrenceFrequency::class,
            'recurrence_interval' => 'integer',
            'recurrence_ends_on' => 'date',
            'occurrence_date' => 'date',
        ];
    }

    /**
     * Get the user the task is assigned to.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the user who created the task.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who completed the task.
     *
     * @return BelongsTo<User, $this>
     */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Get the related meeting.
     *
     * @return BelongsTo<CalendarEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id');
    }

    /**
     * Get the recurring task this occurrence was generated from.
     *
     * @return BelongsTo<Task, $this>
     */
    public function recurrenceParent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'recurrence_parent_id');
    }

    /**
     * Get the occurrences generated from this recurring task.
     *
     * @return HasMany<Task, $this>
     */
    public function occurrences(): HasMany
    {
        return $this->hasMany(Task::class, 'recurrence_parent_id');
    }

    /**
     * Get the task checklist.
     *
     * @return MorphMany<ChecklistItem, $this>
     */
    public function checklistItems(): MorphMany
    {
        return $this->morphMany(ChecklistItem::class, 'checklistable')->orderBy('position')->orderBy('id');
    }

    /**
     * Get the completed checklist items (used for progress counts).
     *
     * @return MorphMany<ChecklistItem, $this>
     */
    public function completedChecklistItems(): MorphMany
    {
        return $this->morphMany(ChecklistItem::class, 'checklistable')->where('is_completed', true);
    }

    /**
     * Get the task attachments.
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest('id');
    }

    /**
     * Get the task reminders.
     *
     * @return MorphMany<Reminder, $this>
     */
    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }

    /**
     * Get the task activity history.
     *
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('id');
    }

    /**
     * Determine whether the task is still open.
     */
    public function isOpen(): bool
    {
        return in_array($this->status->value, TaskStatus::open(), true);
    }

    /**
     * Determine whether the task is past its due time and still open.
     */
    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_at !== null && $this->due_at->isPast();
    }

    /**
     * Determine whether the task defines a recurrence.
     */
    public function isRecurring(): bool
    {
        return $this->recurrence_frequency !== null && $this->recurrence_parent_id === null;
    }

    /**
     * Scope a query to tasks the user may view.
     *
     * @param  Builder<Task>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can(Permission::ManageTasks->value)) {
            return;
        }

        $query->where(function (Builder $query) use ($user): void {
            $query->where('assigned_to', $user->id)->orWhere('created_by', $user->id);
        });
    }

    /**
     * Scope a query to unfinished tasks.
     *
     * @param  Builder<Task>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', TaskStatus::open());
    }

    /**
     * Scope a query to overdue tasks.
     *
     * @param  Builder<Task>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->open()->whereNotNull('due_at')->where('due_at', '<', now());
    }
}
