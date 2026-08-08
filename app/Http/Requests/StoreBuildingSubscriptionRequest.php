<?php

namespace App\Http\Requests;

use App\Enums\SubscriptionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBuildingSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_id' => 'required|integer|exists:buildings,id',
            'plan_id' => 'required|integer|exists:plans,id',
            'starts_at' => 'required|date',
            'expires_at' => 'nullable|date|after:starts_at',
            'status' => ['sometimes', Rule::enum(SubscriptionStatus::class)],
            'limits' => 'nullable|array',
        ];
    }
}
