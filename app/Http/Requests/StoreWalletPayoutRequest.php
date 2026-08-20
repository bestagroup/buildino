<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWalletPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (
            ! $this->filled('idempotency_key')
            && $this->hasHeader('Idempotency-Key')
        ) {
            $this->merge([
                'idempotency_key' => $this->header('Idempotency-Key'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'building_bank_account_id' => [
                'required',
                'integer',
                'exists:building_bank_accounts,id',
            ],
            'amount' => [
                'required',
                'integer',
                'min:1',
            ],
            'idempotency_key' => [
                'nullable',
                'string',
                'max:120',
            ],
        ];
    }
}
