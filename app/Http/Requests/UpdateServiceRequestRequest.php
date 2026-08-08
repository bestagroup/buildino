<?php

namespace App\Http\Requests;

use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_id' => 'sometimes|integer|exists:buildings,id',
            'unit_id' => 'sometimes|nullable|integer|exists:units,id',
            'requested_by' => 'sometimes|integer|exists:users,id',
            'type' => 'sometimes|string|max:50',
            'priority' => ['sometimes', 'sometimes', Rule::enum(ServiceRequestPriority::class)],
            'status' => ['sometimes', 'sometimes', Rule::enum(ServiceRequestStatus::class)],
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'assigned_to' => 'sometimes|nullable|integer|exists:users,id',
        ];
    }
}
