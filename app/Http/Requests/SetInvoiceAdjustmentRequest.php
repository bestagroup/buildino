<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetInvoiceAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'between:-9007199254740991,9007199254740991'],
            'expected_amount' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:2000', 'regex:/\\S/u'],
        ];
    }
}
