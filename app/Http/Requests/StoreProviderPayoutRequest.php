<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProviderPayoutRequest extends FormRequest
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
            'provider_bank_account_id' => [
                'required',
                'integer',
                'exists:provider_bank_accounts,id',
            ],
            'amount' => [
                'required',
                'integer',
                'min:1',
            ],
            'currency' => [
                'nullable',
                'string',
                'size:3',
            ],
            'idempotency_key' => [
                'nullable',
                'string',
                'max:120',
            ],
        ];
    }
}
