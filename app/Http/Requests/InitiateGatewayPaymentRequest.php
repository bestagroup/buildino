<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitiateGatewayPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gateway' => [
                'required',
                'string',
                'max:100',
            ],

            'idempotency_key' => [
                'required',
                'string',
                'max:190',
            ],
        ];
    }
}
