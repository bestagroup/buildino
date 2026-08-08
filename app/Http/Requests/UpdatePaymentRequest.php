<?php

namespace App\Http\Requests;

use App\Enums\PaymentStatus;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_id' => 'sometimes|integer|exists:buildings,id',
            'payer_user_id' => 'sometimes|nullable|integer|exists:users,id',
            'payment_number' => ['sometimes', 'string', 'max:100', Rule::unique('payments', 'payment_number')->ignore($this->route('payment')?->id ?? $this->route('payment'))],
            'amount' => 'sometimes|integer|min:1',
            'currency' => 'sometimes|string|size:3',
            'method' => ['sometimes', 'required', Rule::enum(PaymentMethod::class)],
            'status' => ['sometimes', 'sometimes', Rule::enum(PaymentStatus::class)],
            'paid_at' => 'sometimes|nullable|date',
            'description' => 'sometimes|nullable|string',
        ];
    }
}
