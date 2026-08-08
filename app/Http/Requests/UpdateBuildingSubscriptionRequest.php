<?php

namespace App\Http\Requests;

use App\Enums\SubscriptionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBuildingSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_id' => 'sometimes|integer|exists:buildings,id',
            'plan_id' => 'sometimes|integer|exists:plans,id',
            'starts_at' => 'sometimes|date',
            'expires_at' => 'sometimes|nullable|date|after:starts_at',
            'status' => ['sometimes', 'sometimes', Rule::enum(SubscriptionStatus::class)],
            'limits' => 'sometimes|nullable|array',
        ];
    }
}
