<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClaimLoyaltyRewardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $header = $this->header('Idempotency-Key');

        if (is_string($header) && trim($header) !== '') {
            $this->merge(['idempotency_key' => trim($header)]);
        }
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:191'],
        ];
    }
}
