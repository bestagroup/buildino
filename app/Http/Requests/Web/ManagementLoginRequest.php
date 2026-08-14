<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

class ManagementLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => [
                'required',
                'string',
                'max:190',
            ],
            'password' => [
                'required',
                'string',
                'max:255',
            ],
            'remember' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' =>
                'شماره موبایل یا ایمیل را وارد کنید.',
            'password.required' =>
                'رمز عبور را وارد کنید.',
        ];
    }
}
