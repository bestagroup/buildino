<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBuildingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'complex_id' => 'sometimes|integer|exists:complexes,id',
            'code' => ['sometimes', 'string', 'max:100', Rule::unique('buildings', 'code')->ignore($this->route('building')?->id ?? $this->route('building'))],
            'title' => 'sometimes|string|max:255',
            'building_number' => 'sometimes|nullable|string|max:100',
            'address' => 'sometimes|nullable|string',
            'postal_code' => 'sometimes|nullable|string|max:20',
            'latitude' => 'sometimes|nullable|numeric|between:-90,90',
            'longitude' => 'sometimes|nullable|numeric|between:-180,180',
            'timezone' => 'sometimes|nullable|timezone',
            'currency' => 'sometimes|nullable|string|size:3',
            'floors_count' => 'sometimes|integer|min:0',
            'units_count' => 'sometimes|integer|min:0',
            'parking_count' => 'sometimes|integer|min:0',
            'storage_count' => 'sometimes|integer|min:0',
            'construction_year' => 'sometimes|nullable|integer|min:1200|max:2200',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
