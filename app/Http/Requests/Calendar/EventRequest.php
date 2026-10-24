<?php

namespace App\Http\Requests\Calendar;

use App\Enums\AttendeeRole;
use App\Enums\EventType;
use App\Enums\Priority;
use App\Enums\Role;
use App\Http\Requests\Concerns\ConvertsLocalTimes;
use App\Models\User;
use App\Support\AppSettings;
use App\Support\Timezones;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared validation for creating and updating calendar events.
 */
abstract class EventRequest extends FormRequest
{
    use ConvertsLocalTimes;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'owner_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', Rule::enum(EventType::class)],
            'category' => ['nullable', 'string', 'max:64'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'timezone' => ['required', 'string', Rule::in(Timezones::identifiers())],
            'all_day' => ['boolean'],
            'starts_at' => $this->localTimeRule(true),
            'ends_at' => $this->localTimeRule(true),
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_url' => ['nullable', 'url:https,http', 'max:2048'],
            'agenda' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'preparation_notes' => ['nullable', 'string', 'max:10000'],
            'reminder_minutes' => ['nullable', 'array', 'max:10'],
            'reminder_minutes.*' => ['integer', 'distinct', Rule::in(AppSettings::SUPPORTED_REMINDER_MINUTES)],
            'attendees' => ['array', 'max:50'],
            'attendees.*.user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'attendees.*.name' => ['required_without:attendees.*.user_id', 'nullable', 'string', 'max:255'],
            'attendees.*.email' => ['nullable', 'email', 'max:255'],
            'attendees.*.role' => ['required', Rule::enum(AttendeeRole::class)],
            'acknowledge_conflicts' => ['boolean'],
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
                $this->validateLocalTimesExist($validator, ['starts_at', 'ends_at']);

                if ($validator->errors()->hasAny(['starts_at', 'ends_at', 'timezone'])) {
                    return;
                }

                if (! $this->utc('ends_at')->greaterThan($this->utc('starts_at'))) {
                    $validator->errors()->add('ends_at', 'The end time must be after the start time.');
                }

                if (! $validator->errors()->has('owner_id')) {
                    $owner = User::find($this->integer('owner_id'));

                    if ($owner !== null && ! $owner->hasRole(Role::Executive->value)) {
                        $validator->errors()->add('owner_id', 'Appointments must belong to an executive’s calendar.');
                    }
                }
            },
        ];
    }

    /**
     * Get the event attributes with times converted to UTC.
     *
     * @return array<string, mixed>
     */
    public function eventAttributes(): array
    {
        return [
            ...$this->safe()->only([
                'owner_id', 'title', 'description', 'type', 'category', 'priority', 'timezone',
                'location', 'meeting_url', 'agenda', 'notes', 'preparation_notes',
            ]),
            'all_day' => $this->boolean('all_day'),
            'starts_at' => $this->utc('starts_at'),
            'ends_at' => $this->utc('ends_at'),
            'reminder_minutes' => $this->filled('reminder_minutes')
                ? array_values(array_map('intval', $this->input('reminder_minutes')))
                : null,
        ];
    }

    /**
     * Get the attendee rows, filling internal attendees' names and emails from their accounts.
     *
     * @return array<int, array{user_id: int|null, name: string, email: string|null, role: string}>
     */
    public function attendees(): array
    {
        $rows = $this->safe()->collect('attendees');
        $users = User::query()->whereIn('id', $rows->pluck('user_id')->filter())->get()->keyBy('id');

        return $rows->map(function (array $row) use ($users): array {
            $user = isset($row['user_id']) ? $users->get($row['user_id']) : null;

            return [
                'user_id' => $user?->id,
                'name' => $user->name ?? (string) $row['name'],
                'email' => $user->email ?? ($row['email'] ?? null),
                'role' => $row['role'],
            ];
        })->values()->all();
    }

    /**
     * Determine whether the user confirmed saving despite overlapping appointments.
     */
    public function acknowledgesConflicts(): bool
    {
        return $this->boolean('acknowledge_conflicts');
    }
}
