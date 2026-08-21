<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

final class RequestWebOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'mobile' => $this->normalizeMobile(
                $this->input('mobile')
            ),
        ]);
    }

    public function rules(): array
    {
        return [
            'mobile' => [
                'required',
                'regex:/^09\d{9}$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.required' => 'شماره موبایل را وارد کنید.',
            'mobile.regex' => 'شماره موبایل باید با 09 شروع شود و 11 رقم باشد.',
        ];
    }

    private function normalizeMobile(mixed $value): string
    {
        $value = strtr(
            trim((string) $value),
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
        );

        $value = preg_replace(
            '/[\s\-()]+/',
            '',
            $value
        ) ?? $value;

        if (str_starts_with($value, '+98')) {
            return '0'.substr($value, 3);
        }

        if (str_starts_with($value, '0098')) {
            return '0'.substr($value, 4);
        }

        if (
            str_starts_with($value, '98')
            && strlen($value) === 12
        ) {
            return '0'.substr($value, 2);
        }

        return $value;
    }
}
