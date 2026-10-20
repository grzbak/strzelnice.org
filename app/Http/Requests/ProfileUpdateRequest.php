<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'telephone' => ['nullable', 'string', 'max:50'],
            'has_shooting_leader_license' => ['nullable', 'boolean'],
            'shooting_leader_license_number' => [
                'nullable',
                'required_if:has_shooting_leader_license,1',
                'string',
                'max:100',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'shooting_leader_license_number.required_if' => 'Podaj numer uprawnień lub legitymacji prowadzącego strzelanie.',
        ];
    }
}
