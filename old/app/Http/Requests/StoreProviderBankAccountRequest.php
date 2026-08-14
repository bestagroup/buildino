<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProviderBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_name' => [
                'nullable',
                'string',
                'max:120',
            ],
            'account_holder_name' => [
                'required',
                'string',
                'max:190',
            ],
            'iban' => [
                'required',
                'string',
                'min:10',
                'max:34',
            ],
            'account_number' => [
                'nullable',
                'string',
                'max:50',
            ],
            'card_number' => [
                'nullable',
                'string',
                'max:19',
            ],
            'is_default' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
