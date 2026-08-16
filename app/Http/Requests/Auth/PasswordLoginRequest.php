<?php
namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PasswordLoginRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
            'device_id' => ['nullable', 'string', 'max:255', 'required_with:platform,push_token'],
            'platform' => ['nullable', Rule::in(['ios', 'android', 'web']), 'required_with:device_id'],
            'push_token' => ['nullable', 'string', 'max:4096'],
        ];
    }
}
