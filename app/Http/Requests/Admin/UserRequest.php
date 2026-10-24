<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Models\User;
use App\Support\Timezones;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User
            ? $this->user()->can('update', $user)
            : $this->user()->can('create', User::class);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone'))) {
            $this->merge(['phone' => preg_replace('/[\s\-().]/', '', $this->input('phone')) ?: null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            ...$this->profileRules($user instanceof User ? $user->id : null),
            'timezone' => ['required', 'string', Rule::in(Timezones::identifiers())],
            'phone' => ['nullable', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['distinct', Rule::enum(Role::class)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter the phone number in international format, e.g. +639171234567.',
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
                $user = $this->route('user');

                if ($user instanceof User
                    && $user->is($this->user())
                    && ! in_array(Role::Administrator->value, (array) $this->input('roles', []), true)) {
                    $validator->errors()->add('roles', 'You cannot remove your own administrator role.');
                }
            },
        ];
    }
}
