<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitOwnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_id' => 'sometimes|integer|exists:units,id',
            'user_id' => 'sometimes|integer|exists:users,id',
            'ownership_percentage' => 'sometimes|nullable|numeric|min:0|max:100',
            'starts_at' => 'sometimes|date',
            'ends_at' => 'sometimes|nullable|date|after_or_equal:starts_at',
            'is_primary' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'notes' => 'sometimes|nullable|string',
        ];
    }
}
