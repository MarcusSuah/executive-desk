<?php

namespace App\Http\Requests\Settings;

use App\Enums\NotificationChannel;
use App\Support\AppSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class NotificationPreferenceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $minutes = ['integer', 'distinct', Rule::in(AppSettings::SUPPORTED_REMINDER_MINUTES)];
        $channels = ['string', 'distinct', Rule::enum(NotificationChannel::class)];

        return [
            'email_enabled' => ['required', 'boolean'],
            'sms_enabled' => ['required', 'boolean'],
            'push_enabled' => ['required', 'boolean'],
            'event_reminder_minutes' => ['present', 'array', 'max:10'],
            'event_reminder_minutes.*' => $minutes,
            'task_reminder_minutes' => ['present', 'array', 'max:10'],
            'task_reminder_minutes.*' => $minutes,
            'presentation_reminder_minutes' => ['present', 'array', 'max:10'],
            'presentation_reminder_minutes.*' => $minutes,
            'schedule_change_notifications' => ['required', 'boolean'],
            'daily_briefing_enabled' => ['required', 'boolean'],
            'daily_briefing_time' => ['required', 'date_format:H:i'],
            'daily_briefing_channels' => ['array', 'required_if_accepted:daily_briefing_enabled'],
            'daily_briefing_channels.*' => $channels,
            'weekly_briefing_enabled' => ['required', 'boolean'],
            'weekly_briefing_day' => ['required', 'integer', 'between:1,7'],
            'weekly_briefing_time' => ['required', 'date_format:H:i'],
            'weekly_briefing_channels' => ['array', 'required_if_accepted:weekly_briefing_enabled'],
            'weekly_briefing_channels.*' => $channels,
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->boolean('sms_enabled') && ! $this->user()->hasVerifiedPhone()) {
                    $validator->errors()->add('sms_enabled', 'Verify your mobile number before turning on SMS notifications.');
                }

                if ($this->boolean('push_enabled') && ! $this->user()->pushSubscriptions()->exists()) {
                    $validator->errors()->add('push_enabled', 'Enable push notifications on this device first.');
                }
            },
        ];
    }

    /**
     * Get the preference attributes to store.
     *
     * @return array<string, mixed>
     */
    public function preferenceAttributes(): array
    {
        $sortedMinutes = fn (string $key): array => $this->safe()->collect($key)
            ->map(fn ($value) => (int) $value)
            ->sortDesc()
            ->values()
            ->all();

        return [
            'email_enabled' => $this->boolean('email_enabled'),
            'sms_enabled' => $this->boolean('sms_enabled'),
            'push_enabled' => $this->boolean('push_enabled'),
            'event_reminder_minutes' => $sortedMinutes('event_reminder_minutes'),
            'task_reminder_minutes' => $sortedMinutes('task_reminder_minutes'),
            'presentation_reminder_minutes' => $sortedMinutes('presentation_reminder_minutes'),
            'schedule_change_notifications' => $this->boolean('schedule_change_notifications'),
            'daily_briefing_enabled' => $this->boolean('daily_briefing_enabled'),
            'daily_briefing_time' => $this->input('daily_briefing_time').':00',
            'daily_briefing_channels' => array_values($this->validated('daily_briefing_channels', [])),
            'weekly_briefing_enabled' => $this->boolean('weekly_briefing_enabled'),
            'weekly_briefing_day' => $this->integer('weekly_briefing_day'),
            'weekly_briefing_time' => $this->input('weekly_briefing_time').':00',
            'weekly_briefing_channels' => array_values($this->validated('weekly_briefing_channels', [])),
        ];
    }
}
