<?php

namespace App\Http\Requests;

use App\Enums\FacilityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBuildingFacilityRequest extends FormRequest
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
            'code' => 'required|string|max:100',
            'description' => 'nullable|string',
            'type' => ['required', Rule::enum(FacilityType::class)],
            'capacity' => 'nullable|integer|min:1',
            'default_price' => 'sometimes|integer|min:0',
            'requires_payment' => 'sometimes|boolean',
            'requires_approval' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
