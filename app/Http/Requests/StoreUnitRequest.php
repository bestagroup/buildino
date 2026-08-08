<?php

namespace App\Http\Requests;

use App\Enums\UnitUsageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'floor_id' => 'required|integer|exists:floors,id',
            'unit_number' => 'required|string|max:100',
            'title' => 'nullable|string|max:255',
            'area' => 'nullable|numeric|min:0',
            'bedrooms' => 'nullable|integer|min:0|max:50',
            'usage_type' => ['required', Rule::enum(UnitUsageType::class)],
            'is_active' => 'sometimes|boolean',
        ];
    }
}
