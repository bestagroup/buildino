<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportDateRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => [
                'nullable',
                'date',
            ],

            'to' => [
                'nullable',
                'date',
            ],

            'granularity' => [
                'nullable',
                Rule::in([
                    'day',
                    'month',
                ]),
            ],

            'currency' => [
                'nullable',
                'string',
                'size:3',
            ],
        ];
    }
}
