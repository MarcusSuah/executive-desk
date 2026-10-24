<?php

namespace App\Http\Requests\Settings;

use App\Support\AppSettings;
use App\Support\NotificationCategories;
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

        $rules = [
            'in_app_enabled' => ['required', 'boolean'],
            'email_enabled' => ['required', 'boolean'],
            'sms_enabled' => ['required', 'boolean'],
            'push_enabled' => ['required', 'boolean'],
            'categories' => ['required', 'array:'.implode(',', NotificationCategories::keys())],
            'event_reminder_minutes' => ['present', 'array', 'max:10'],
            'event_reminder_minutes.*' => $minutes,
            'task_reminder_minutes' => ['present', 'array', 'max:10'],
            'task_reminder_minutes.*' => $minutes,
            'presentation_reminder_minutes' => ['present', 'array', 'max:10'],
            'presentation_reminder_minutes.*' => $minutes,
            'daily_briefing_time' => ['required', 'date_format:H:i'],
            'weekly_briefing_day' => ['required', 'integer', 'between:1,7'],
            'weekly_briefing_time' => ['required', 'date_format:H:i'],
            'quiet_hours_enabled' => ['required', 'boolean'],
            'quiet_hours_start' => ['required', 'date_format:H:i'],
            'quiet_hours_end' => ['required', 'date_format:H:i', 'different:quiet_hours_start'],
            'quiet_hours_allow_high' => ['required', 'boolean'],
            'digest_frequency' => ['required', Rule::in(['off', 'daily', 'weekly'])],
            'digest_time' => ['required', 'date_format:H:i'],
            'digest_day' => ['required', 'integer', 'between:1,7'],
            'digest_include' => ['required', Rule::in(['non_critical', 'low_only'])],
        ];

        foreach (NotificationCategories::keys() as $category) {
            $rules["categories.{$category}"] = ['required', 'array'];

            foreach (NotificationCategories::CHANNELS as $channel) {
                $rules["categories.{$category}.{$channel}"] = ['required', 'boolean'];
            }

            $rules["categories.{$category}.priority"] = ['required', Rule::in(NotificationCategories::PRIORITIES)];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quiet_hours_end.different' => 'Quiet hours must end at a different time than they start.',
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

        $categories = [];

        foreach (NotificationCategories::keys() as $category) {
            foreach (NotificationCategories::CHANNELS as $channel) {
                $categories[$category][$channel] = $this->boolean("categories.{$category}.{$channel}");
            }

            $categories[$category]['priority'] = $this->string("categories.{$category}.priority")->toString();
        }

        return [
            'in_app_enabled' => $this->boolean('in_app_enabled'),
            'email_enabled' => $this->boolean('email_enabled'),
            'sms_enabled' => $this->boolean('sms_enabled'),
            'push_enabled' => $this->boolean('push_enabled'),
            'categories' => $categories,
            'event_reminder_minutes' => $sortedMinutes('event_reminder_minutes'),
            'task_reminder_minutes' => $sortedMinutes('task_reminder_minutes'),
            'presentation_reminder_minutes' => $sortedMinutes('presentation_reminder_minutes'),
            'daily_briefing_time' => $this->input('daily_briefing_time').':00',
            'weekly_briefing_day' => $this->integer('weekly_briefing_day'),
            'weekly_briefing_time' => $this->input('weekly_briefing_time').':00',
            'quiet_hours_enabled' => $this->boolean('quiet_hours_enabled'),
            'quiet_hours_start' => $this->input('quiet_hours_start').':00',
            'quiet_hours_end' => $this->input('quiet_hours_end').':00',
            'quiet_hours_allow_high' => $this->boolean('quiet_hours_allow_high'),
            'digest_frequency' => $this->string('digest_frequency')->toString(),
            'digest_time' => $this->input('digest_time').':00',
            'digest_day' => $this->integer('digest_day'),
            'digest_include' => $this->string('digest_include')->toString(),
        ];
    }
}
