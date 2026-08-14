<?php

namespace App\Http\Requests;

use App\Enums\ReportFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateReportExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_id' => [
                'nullable',
                'integer',
                'exists:buildings,id',
            ],

            'format' => [
                'required',
                Rule::enum(
                    ReportFormat::class
                ),
            ],

            'from' => [
                'nullable',
                'date',
            ],

            'to' => [
                'nullable',
                'date',
            ],

            'as_of' => [
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
