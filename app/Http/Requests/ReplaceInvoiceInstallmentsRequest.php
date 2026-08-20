<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceInvoiceInstallmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'installments' => ['required', 'array', 'min:2', 'max:60'],
            'installments.*.due_date' => [
                'required',
                'date',
                'after_or_equal:today',
                'distinct',
            ],
            'installments.*.amount' => ['required', 'integer', 'min:1'],
            'installments.*.metadata' => ['nullable', 'array'],
        ];
    }
}
