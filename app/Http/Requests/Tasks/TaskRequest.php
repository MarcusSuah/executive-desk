<?php

namespace App\Http\Requests\Tasks;

use App\Enums\Priority;
use App\Enums\RecurrenceFrequency;
use App\Http\Requests\Concerns\ConvertsLocalTimes;
use App\Models\CalendarEvent;
use App\Support\Timezones;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared validation for creating and editing tasks.
 */
abstract class TaskRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'calendar_event_id' => ['nullable', 'integer', Rule::exists('calendar_events', 'id')->whereNull('deleted_at')],
            'project' => ['nullable', 'string', 'max:255'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'timezone' => ['required', 'string', Rule::in(Timezones::identifiers())],
            'starts_at' => $this->localTimeRule(false),
            'due_at' => [...$this->localTimeRule(false), 'required_with:recurrence_frequency'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'recurrence_frequency' => ['nullable', Rule::enum(RecurrenceFrequency::class)],
            'recurrence_interval' => ['nullable', 'integer', 'min:1', 'max:30'],
            'recurrence_ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
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
                $this->validateLocalTimesExist($validator, ['starts_at', 'due_at']);

                if ($validator->errors()->hasAny(['starts_at', 'due_at', 'timezone'])) {
                    return;
                }

                $startsAt = $this->utc('starts_at');
                $dueAt = $this->utc('due_at');

                if ($startsAt !== null && $dueAt !== null && $dueAt->lessThan($startsAt)) {
                    $validator->errors()->add('due_at', 'The due date must be after the start date.');
                }

                if ($this->filled('calendar_event_id') && ! $validator->errors()->has('calendar_event_id')) {
                    $event = CalendarEvent::find($this->integer('calendar_event_id'));

                    if ($event === null || ! $this->user()->can('view', $event)) {
                        $validator->errors()->add('calendar_event_id', 'The selected meeting is not available.');
                    }
                }
            },
        ];
    }

    /**
     * Get the task attributes with times converted to UTC.
     *
     * @return array<string, mixed>
     */
    public function taskAttributes(): array
    {
        $recurring = $this->filled('recurrence_frequency');

        return [
            ...$this->safe()->only(['title', 'description', 'assigned_to', 'calendar_event_id', 'project', 'priority', 'timezone', 'estimated_minutes', 'notes']),
            'starts_at' => $this->utc('starts_at'),
            'due_at' => $this->utc('due_at'),
            'recurrence_frequency' => $recurring ? $this->input('recurrence_frequency') : null,
            'recurrence_interval' => $recurring ? max(1, $this->integer('recurrence_interval', 1)) : null,
            'recurrence_ends_on' => $recurring ? $this->input('recurrence_ends_on') : null,
        ];
    }
}
