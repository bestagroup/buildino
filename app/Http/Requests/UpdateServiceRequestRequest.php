<?php

namespace App\Http\Requests;

use App\Enums\ServiceRequestPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequestRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'building_id' => ['sometimes', 'integer', 'exists:buildings,id'],
            'unit_id' => ['sometimes', 'nullable', 'integer', 'exists:units,id'],
            'type' => ['sometimes', 'string', 'max:50'],
            'priority' => ['sometimes', Rule::enum(ServiceRequestPriority::class)],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
        ];
    }
}
