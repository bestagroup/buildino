<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitOwnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_id' => 'required|integer|exists:units,id',
            'user_id' => 'required|integer|exists:users,id',
            'ownership_percentage' => 'nullable|numeric|min:0|max:100',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_primary' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ];
    }
}
