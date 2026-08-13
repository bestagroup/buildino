<?php

namespace App\Http\Requests;

use App\Enums\UnitUsageType;
use App\Models\Floor;
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
        $floor = $this->route('floor');

        $floorId = $floor instanceof Floor
            ? $floor->getKey()
            : (int) $floor;

        return [
            'unit_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('units', 'unit_number')
                    ->where(
                        fn ($query) => $query->where(
                            'floor_id',
                            $floorId
                        )
                    ),
            ],

            'title' => [
                'nullable',
                'string',
                'max:255',
            ],

            'area' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'bedrooms' => [
                'nullable',
                'integer',
                'min:0',
                'max:50',
            ],

            'usage_type' => [
                'required',
                Rule::enum(UnitUsageType::class),
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
