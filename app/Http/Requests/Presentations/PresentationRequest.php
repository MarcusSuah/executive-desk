<?php

namespace App\Http\Requests\Presentations;

use App\Enums\PresentationStatus;
use App\Enums\Role;
use App\Http\Requests\Concerns\ConvertsLocalTimes;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Support\Timezones;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared validation for creating and editing presentations.
 */
abstract class PresentationRequest extends FormRequest
{
    use ConvertsLocalTimes;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $editableStatuses = array_values(array_filter(
            PresentationStatus::values(),
            fn (string $status) => $status !== PresentationStatus::Approved->value,
        ));

        return [
            'owner_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'calendar_event_id' => ['nullable', 'integer', Rule::exists('calendar_events', 'id')->whereNull('deleted_at')],
            'presenter_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'presenter_name' => ['nullable', 'string', 'max:255'],
            'timezone' => ['required', 'string', Rule::in(Timezones::identifiers())],
            'presentation_at' => $this->localTimeRule(false),
            'preparation_deadline_at' => $this->localTimeRule(false),
            'review_deadline_at' => $this->localTimeRule(false),
            'venue' => ['nullable', 'string', 'max:255'],
            'meeting_url' => ['nullable', 'url:https,http', 'max:2048'],
            'status' => ['required', Rule::in($editableStatuses)],
            'notes' => ['nullable', 'string', 'max:10000'],
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
                $fields = ['presentation_at', 'preparation_deadline_at', 'review_deadline_at'];

                $this->validateLocalTimesExist($validator, $fields);

                if ($validator->errors()->hasAny([...$fields, 'timezone'])) {
                    return;
                }

                $presentationAt = $this->utc('presentation_at');

                foreach (['preparation_deadline_at' => 'preparation deadline', 'review_deadline_at' => 'review deadline'] as $field => $label) {
                    $deadline = $this->utc($field);

                    if ($presentationAt !== null && $deadline !== null && $deadline->greaterThan($presentationAt)) {
                        $validator->errors()->add($field, "The {$label} must be before the presentation.");
                    }
                }

                $owner = User::find($this->integer('owner_id'));

                if ($owner !== null && ! $owner->hasRole(Role::Executive->value)) {
                    $validator->errors()->add('owner_id', 'Presentations must belong to an executive.');
                }

                if ($this->filled('calendar_event_id')) {
                    $event = CalendarEvent::find($this->integer('calendar_event_id'));

                    if ($event === null || ! $this->user()->can('view', $event)) {
                        $validator->errors()->add('calendar_event_id', 'The selected appointment is not available.');
                    }
                }
            },
        ];
    }

    /**
     * Get the presentation attributes with times converted to UTC.
     *
     * @return array<string, mixed>
     */
    public function presentationAttributes(): array
    {
        return [
            ...$this->safe()->only([
                'owner_id', 'title', 'description', 'calendar_event_id', 'presenter_id', 'presenter_name',
                'timezone', 'venue', 'meeting_url', 'status', 'notes',
            ]),
            'presentation_at' => $this->utc('presentation_at'),
            'preparation_deadline_at' => $this->utc('preparation_deadline_at'),
            'review_deadline_at' => $this->utc('review_deadline_at'),
        ];
    }
}
