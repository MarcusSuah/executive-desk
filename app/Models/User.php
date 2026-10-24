<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $timezone
 * @property string|null $phone
 * @property Carbon|null $phone_verified_at
 * @property bool $is_active
 * @property Carbon|null $deactivated_at
 * @property Carbon|null $last_login_at
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read NotificationPreference|null $notificationPreference
 */
#[Fillable(['name', 'email', 'password', 'timezone', 'phone'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Get the events on the user's calendar.
     *
     * @return HasMany<CalendarEvent, $this>
     */
    public function ownedEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class, 'owner_id');
    }

    /**
     * Get the tasks assigned to the user.
     *
     * @return HasMany<Task, $this>
     */
    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    /**
     * Get the user's notification preferences.
     *
     * @return HasOne<NotificationPreference, $this>
     */
    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    /**
     * Get the user's registered push subscriptions.
     *
     * @return HasMany<PushSubscription, $this>
     */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /**
     * Get the user's reminders.
     *
     * @return HasMany<Reminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    /**
     * Get the delivery attempts made to the user.
     *
     * @return HasMany<NotificationDelivery, $this>
     */
    public function notificationDeliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    /**
     * Get the user's briefings.
     *
     * @return HasMany<ExecutiveBriefing, $this>
     */
    public function briefings(): HasMany
    {
        return $this->hasMany(ExecutiveBriefing::class);
    }

    /**
     * Get the user's notification preferences, creating defaults when missing.
     */
    public function preferences(): NotificationPreference
    {
        if ($this->notificationPreference === null) {
            $preference = $this->notificationPreference()->firstOrCreate(
                [],
                NotificationPreference::defaultAttributesFor($this),
            );

            $this->setRelation('notificationPreference', $preference);
        }

        return $this->notificationPreference;
    }

    /**
     * Determine whether the user has a verified phone number for SMS.
     */
    public function hasVerifiedPhone(): bool
    {
        return $this->phone !== null && $this->phone_verified_at !== null;
    }

    /**
     * Determine whether the user can receive notifications on the given channel.
     */
    public function canReceiveOn(NotificationChannel $channel): bool
    {
        $preferences = $this->preferences();

        return match ($channel) {
            NotificationChannel::Mail => $preferences->email_enabled,
            NotificationChannel::Sms => $preferences->sms_enabled && $this->hasVerifiedPhone(),
            NotificationChannel::Push => $preferences->push_enabled,
        };
    }

    /**
     * Get the channels the user has enabled and can currently receive on.
     *
     * @return array<int, NotificationChannel>
     */
    public function enabledChannels(): array
    {
        return array_values(array_filter(
            NotificationChannel::cases(),
            fn (NotificationChannel $channel): bool => $this->canReceiveOn($channel),
        ));
    }

    /**
     * Scope a query to active users.
     *
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope a query to users holding the executive role.
     *
     * @param  Builder<User>  $query
     */
    public function scopeExecutives(Builder $query): void
    {
        $query->role(Role::Executive->value);
    }
}
