<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

final class VerifyWebOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtr(
                trim((string) $this->input('code')),
                [
                    '۰' => '0', '۱' => '1',
                    '۲' => '2', '۳' => '3',
                    '۴' => '4', '۵' => '5',
                    '۶' => '6', '۷' => '7',
                    '۸' => '8', '۹' => '9',
                    '٠' => '0', '١' => '1',
                    '٢' => '2', '٣' => '3',
                    '٤' => '4', '٥' => '5',
                    '٦' => '6', '٧' => '7',
                    '٨' => '8', '٩' => '9',
                ]
            ),
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'digits_between:4,8',
            ],
            'auth_method' => [
                'nullable',
                'in:otp',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'کد تأیید پیامک‌شده را وارد کنید.',
            'code.digits_between' => 'کد تأیید باید بین ۴ تا ۸ رقم باشد.',
        ];
    }
}
