<?php

namespace App\Http\Requests;

use App\Enums\FacilityType;
use App\Models\Building;
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
        $building = $this->route('building');

        $buildingId = $building instanceof Building
            ? $building->getKey()
            : (int) $building;

        return [
            'title' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('building_facilities', 'code')
                    ->where(fn ($query) => $query->where('building_id', $buildingId)),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'string', 'max:2048'],
            'type' => ['required', Rule::enum(FacilityType::class)],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'default_price' => ['sometimes', 'integer', 'min:0'],
            'requires_payment' => ['sometimes', 'boolean'],
            'requires_approval' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
