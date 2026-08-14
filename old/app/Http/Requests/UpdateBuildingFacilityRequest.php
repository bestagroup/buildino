<?php

namespace App\Http\Requests;

use App\Enums\FacilityType;
use App\Models\BuildingFacility;
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
        $facility = $this->route('buildingFacility');

        if (! $facility instanceof BuildingFacility) {
            $facility = BuildingFacility::query()->find($facility);
        }

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('building_facilities', 'code')
                    ->where(fn ($query) => $query->where(
                        'building_id',
                        $facility?->building_id
                    ))
                    ->ignore($facility?->getKey()),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'type' => ['sometimes', 'required', Rule::enum(FacilityType::class)],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'default_price' => ['sometimes', 'integer', 'min:0'],
            'requires_payment' => ['sometimes', 'boolean'],
            'requires_approval' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
