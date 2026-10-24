<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;

/**
 * Option lists shared by forms and filters.
 */
class FormOptions
{
    /**
     * Get the active executives.
     *
     * @return array<int, array{id: int, name: string, timezone: string}>
     */
    public static function executives(): array
    {
        return User::query()
            ->active()
            ->role(Role::Executive->value)
            ->orderBy('name')
            ->get(['id', 'name', 'timezone'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'timezone' => $user->timezone])
            ->all();
    }

    /**
     * Get every active user (for attendees, assignees, and presenters).
     *
     * @return array<int, array{id: int, name: string, email: string}>
     */
    public static function users(): array
    {
        return User::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email])
            ->all();
    }

    /**
     * Get the IANA time-zone identifiers.
     *
     * @return array<int, string>
     */
    public static function timezones(): array
    {
        return Timezones::identifiers();
    }

    /**
     * Get the selectable reminder intervals.
     *
     * @return array<int, array{value: int, label: string}>
     */
    public static function reminderMinutes(): array
    {
        return array_map(
            fn (int $minutes) => ['value' => $minutes, 'label' => DisplayTime::minutes($minutes).' before'],
            AppSettings::SUPPORTED_REMINDER_MINUTES,
        );
    }
}
