<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Enums\WalletTopUpTargetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWalletTopUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_type' => [
                'required',
                Rule::enum(WalletTopUpTargetType::class),
            ],

            'unit_id' => [
                'nullable',
                'integer',
                'required_if:target_type,unit_wallet',
                'exists:units,id',
            ],

            'amount' => [
                'required',
                'integer',
                'min:1',
            ],

            'method' => [
                'required',
                Rule::enum(PaymentMethod::class),
            ],

            /*
             * Client/gateway supplied uniqueness key. Repeating the same
             * request returns the original WalletTopUp instead of creating
             * another external payment.
             */
            'idempotency_key' => [
                'required',
                'string',
                'max:190',
            ],

            'gateway' => [
                'nullable',
                'string',
                'max:100',
            ],

            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}
