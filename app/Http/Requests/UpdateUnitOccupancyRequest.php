<?php

namespace App\Http\Requests;

use App\Enums\OccupancyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitOccupancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],

            'occupancy_type' => [
                'sometimes',
                'required',
                Rule::enum(OccupancyType::class),
            ],

            'starts_at' => [
                'sometimes',
                'date',
            ],

            'is_primary' => [
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
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}
