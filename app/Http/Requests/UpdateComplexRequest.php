<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateComplexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'string', 'max:100', Rule::unique('complexes', 'code')->ignore($this->route('complexe')?->id ?? $this->route('complexe'))],
            'title' => 'sometimes|string|max:255',
            'province' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:255',
            'address' => 'sometimes|nullable|string',
            'postal_code' => 'sometimes|nullable|string|max:20',
            'latitude' => 'sometimes|nullable|numeric|between:-90,90',
            'longitude' => 'sometimes|nullable|numeric|between:-180,180',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
