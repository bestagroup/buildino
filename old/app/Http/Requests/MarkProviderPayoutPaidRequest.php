<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkProviderPayoutPaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_reference' => [
                'required',
                'string',
                'max:190',
            ],
        ];
    }
}
