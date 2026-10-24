<?php

namespace App\Http\Requests;

use App\Enums\OccupancyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitOccupancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],

            'occupancy_type' => [
                'required',
                Rule::enum(OccupancyType::class),
            ],

            'starts_at' => [
                'required',
                'date',
            ],

            'ends_at' => [
                'nullable',
                'date',
                'after_or_equal:starts_at',
            ],

            'is_primary' => [
                'sometimes',
                'boolean',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],

            'resident_count' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
                'max:255',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}
