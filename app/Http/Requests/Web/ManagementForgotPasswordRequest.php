<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

class ManagementForgotPasswordRequest extends FormRequest
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
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' =>
                'شماره موبایل یا ایمیل را وارد کنید.',
        ];
    }
}
