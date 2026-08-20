<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoyaltyRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_type' => ['required', Rule::in(['payment_verified'])],
            'points' => ['required', 'integer', 'min:1', 'max:1000000'],
            'configuration' => ['nullable', 'array'],
            'configuration.amount_step' => ['nullable', 'integer', 'min:1'],
            'configuration.minimum_amount' => ['nullable', 'integer', 'min:0'],
            'configuration.maximum_points' => ['nullable', 'integer', 'min:1'],
            'configuration.expires_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
