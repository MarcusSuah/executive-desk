<?php

namespace App\Http\Requests\Presentations;

use App\Models\Presentation;
use Illuminate\Contracts\Validation\ValidationRule;

class StorePresentationRequest extends PresentationRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Presentation::class);
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
     * Get the checklist items.
     *
     * @return array<int, string>
     */
    public function checklist(): array
    {
        return array_values(array_filter(array_map('trim', $this->validated('checklist', []))));
    }
}
