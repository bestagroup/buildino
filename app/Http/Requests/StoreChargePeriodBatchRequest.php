<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreChargePeriodBatchRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'between:1404,1410'],
            'months' => ['required', 'array', 'min:1', 'max:12'],
            'months.*' => ['required', 'integer', 'between:1,12', 'distinct'],
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_end'],
        ];
    }
}
