<?php

namespace App\Http\Requests;

use App\Enums\ChargePeriodStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChargePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_id' => 'sometimes|integer|exists:buildings,id',
            'title' => 'sometimes|string|max:255',
            'period_start' => 'sometimes|date',
            'period_end' => 'sometimes|date|after_or_equal:period_start',
            'due_date' => 'sometimes|date|after_or_equal:period_start',
            'status' => ['sometimes', 'sometimes', Rule::enum(ChargePeriodStatus::class)],
        ];
    }
}
