<?php

namespace App\Http\Requests;

use App\Enums\PaymentStatus;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_id' => 'required|integer|exists:buildings,id',
            'payer_user_id' => 'nullable|integer|exists:users,id',
            'payment_number' => 'required|string|max:100|unique:payments,payment_number',
            'amount' => 'required|integer|min:1',
            'currency' => 'sometimes|string|size:3',
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'status' => ['sometimes', Rule::enum(PaymentStatus::class)],
            'paid_at' => 'nullable|date',
            'description' => 'nullable|string',
        ];
    }
}
