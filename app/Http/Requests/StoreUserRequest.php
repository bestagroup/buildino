<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'national_code' => 'nullable|string|max:20|unique:users,national_code',
            'mobile' => 'nullable|string|max:20|unique:users,mobile|required_without:email',
            'email' => 'nullable|email|max:255|unique:users,email|required_without:mobile',
            'password' => 'nullable|string|min:8|max:255',
            'is_active' => 'sometimes|boolean',
            'is_blocked' => 'sometimes|boolean',
        ];
    }
}
