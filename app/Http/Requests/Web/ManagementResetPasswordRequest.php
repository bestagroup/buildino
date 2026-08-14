<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ManagementResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => [
                'required',
                'string',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
            ],

            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->numbers(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' =>
                'توکن بازنشانی رمز عبور معتبر نیست.',

            'email.required' =>
                'ایمیل حساب کاربری الزامی است.',

            'email.email' =>
                'فرمت ایمیل معتبر نیست.',

            'password.required' =>
                'رمز عبور جدید را وارد کنید.',

            'password.confirmed' =>
                'تکرار رمز عبور با رمز جدید یکسان نیست.',

            'password.min' =>
                'رمز عبور باید حداقل ۸ کاراکتر باشد.',
        ];
    }
}
