<?php

namespace App\Http\Requests\Presentations;

class UpdatePresentationRequest extends PresentationRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('presentation'));
    }
}
