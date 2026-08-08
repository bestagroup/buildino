<?php

namespace App\Http\Requests;

use App\Enums\ChargePeriodStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChargePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_id' => 'required|integer|exists:buildings,id',
            'title' => 'required|string|max:255',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'due_date' => 'required|date|after_or_equal:period_start',
            'status' => ['sometimes', Rule::enum(ChargePeriodStatus::class)],
        ];
    }
}
