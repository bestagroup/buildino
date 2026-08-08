<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'national_code' => ['sometimes', 'nullable', 'string', 'max:20', Rule::unique('users', 'national_code')->ignore($this->route('user')?->id ?? $this->route('user'))],
            'mobile' => ['sometimes', 'nullable', 'string', 'max:20', 'required_without:email', Rule::unique('users', 'mobile')->ignore($this->route('user')?->id ?? $this->route('user'))],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', 'required_without:mobile', Rule::unique('users', 'email')->ignore($this->route('user')?->id ?? $this->route('user'))],
            'password' => 'sometimes|nullable|string|min:8|max:255',
            'is_active' => 'sometimes|boolean',
            'is_blocked' => 'sometimes|boolean',
        ];
    }
}
