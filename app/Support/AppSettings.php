<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Administrator-configurable application settings stored in the app_settings table.
 */
class AppSettings
{
    public const EVENT_REMINDER_MINUTES = 'reminders.event_minutes';

    public const TASK_REMINDER_MINUTES = 'reminders.task_minutes';

    public const PRESENTATION_REMINDER_MINUTES = 'reminders.presentation_minutes';

    public const DEFAULT_TIMEZONE = 'users.default_timezone';

    /**
     * The reminder intervals (in minutes) users may choose from.
     *
     * @var array<int, int>
     */
    public const SUPPORTED_REMINDER_MINUTES = [5, 10, 15, 30, 60, 120, 240, 1440, 2880, 10080];

    private const CACHE_KEY = 'app-settings';

    /**
     * Get a setting value.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return static::all()[$key] ?? $default;
    }

    /**
     * Store a setting value.
     */
    public static function set(string $key, mixed $value): void
    {
        AppSetting::updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Get all stored settings keyed by name.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            now()->addHour(),
            fn (): array => AppSetting::query()->pluck('value', 'key')->all(),
        );
    }

    /**
     * Get the default meeting reminder intervals.
     *
     * @return array<int, int>
     */
    public static function defaultEventReminderMinutes(): array
    {
        return static::get(self::EVENT_REMINDER_MINUTES, [60, 30, 15, 5]);
    }

    /**
     * Get the default task deadline reminder intervals.
     *
     * @return array<int, int>
     */
    public static function defaultTaskReminderMinutes(): array
    {
        return static::get(self::TASK_REMINDER_MINUTES, [1440, 60]);
    }

    /**
     * Get the default presentation deadline reminder intervals.
     *
     * @return array<int, int>
     */
    public static function defaultPresentationReminderMinutes(): array
    {
        return static::get(self::PRESENTATION_REMINDER_MINUTES, [2880, 1440]);
    }

    /**
     * Get the default time zone for new users.
     */
    public static function defaultTimezone(): string
    {
        return static::get(self::DEFAULT_TIMEZONE, config('app.default_user_timezone', 'UTC'));
    }
}
