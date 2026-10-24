<?php

namespace App\Models;

use App\Enums\AttendeeResponse;
use App\Enums\AttendeeRole;
use Database\Factories\EventAttendeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $calendar_event_id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $email
 * @property AttendeeRole $role
 * @property AttendeeResponse $response_status
 */
#[Fillable(['user_id', 'name', 'email', 'role', 'response_status'])]
class EventAttendee extends Model
{
    /** @use HasFactory<EventAttendeeFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => AttendeeRole::class,
            'response_status' => AttendeeResponse::class,
        ];
    }

    /**
     * Get the event the attendee belongs to.
     *
     * @return BelongsTo<CalendarEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id');
    }

    /**
     * Get the internal user, when the attendee is one.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
