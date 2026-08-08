<?php

namespace App\Http\Requests;

use App\Enums\FacilityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBuildingFacilityRequest extends FormRequest
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
            'code' => 'sometimes|string|max:100',
            'description' => 'sometimes|nullable|string',
            'type' => ['sometimes', 'required', Rule::enum(FacilityType::class)],
            'capacity' => 'sometimes|nullable|integer|min:1',
            'default_price' => 'sometimes|integer|min:0',
            'requires_payment' => 'sometimes|boolean',
            'requires_approval' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
