<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform' => [
                'sometimes',
                Rule::in([
                    'ios',
                    'android',
                    'web',
                ]),
            ],

            'device_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'push_token' => [
                'sometimes',
                'nullable',
                'string',
                'max:4096',
            ],
        ];
    }
}
