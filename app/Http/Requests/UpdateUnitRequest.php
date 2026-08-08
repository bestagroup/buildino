<?php

namespace App\Http\Requests;

use App\Enums\UnitUsageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'floor_id' => 'sometimes|integer|exists:floors,id',
            'unit_number' => 'sometimes|string|max:100',
            'title' => 'sometimes|nullable|string|max:255',
            'area' => 'sometimes|nullable|numeric|min:0',
            'bedrooms' => 'sometimes|nullable|integer|min:0|max:50',
            'usage_type' => ['sometimes', 'required', Rule::enum(UnitUsageType::class)],
            'is_active' => 'sometimes|boolean',
        ];
    }
}
