<?php

namespace App\Http\Requests;

use App\Enums\UnitUsageType;
use App\Models\Unit;
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
        $unit = $this->route('unit');

        if (! $unit instanceof Unit) {
            $unit = Unit::query()->find($unit);
        }

        return [
            'unit_number' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('units', 'unit_number')
                    ->where(
                        fn ($query) => $query->where(
                            'floor_id',
                            $unit?->floor_id
                        )
                    )
                    ->ignore($unit?->getKey()),
            ],

            'title' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'area' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
            ],

            'bedrooms' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'max:50',
            ],

            'usage_type' => [
                'sometimes',
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
