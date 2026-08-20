<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $header = $this->header('Idempotency-Key');

        if (is_string($header) && trim($header) !== '') {
            $this->merge([
                'idempotency_key' => trim($header),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'description' => ['nullable', 'string', 'max:5000'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ];
    }
}
