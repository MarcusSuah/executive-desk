<?php

namespace App\Http\Requests\Calendar;

use App\Models\CalendarEvent;
use Illuminate\Contracts\Validation\ValidationRule;

class StoreEventRequest extends EventRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', CalendarEvent::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'checklist' => ['array', 'max:50'],
            'checklist.*' => ['string', 'max:500'],
        ];
    }

    /**
     * Get the preparation checklist items.
     *
     * @return array<int, string>
     */
    public function checklist(): array
    {
        return array_values(array_filter(array_map('trim', $this->validated('checklist', []))));
    }
}
