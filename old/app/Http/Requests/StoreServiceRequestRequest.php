<?php

namespace App\Http\Requests;

use App\Enums\ServiceRequestPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequestRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'building_id' => ['required', 'integer', 'exists:buildings,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'type' => ['required', 'string', 'max:50'],
            'priority' => ['sometimes', Rule::enum(ServiceRequestPriority::class)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
