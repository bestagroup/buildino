<?php

namespace App\Http\Requests;

use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_id' => 'required|integer|exists:buildings,id',
            'unit_id' => 'nullable|integer|exists:units,id',
            'requested_by' => 'required|integer|exists:users,id',
            'type' => 'required|string|max:50',
            'priority' => ['sometimes', Rule::enum(ServiceRequestPriority::class)],
            'status' => ['sometimes', Rule::enum(ServiceRequestStatus::class)],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assigned_to' => 'nullable|integer|exists:users,id',
        ];
    }
}
