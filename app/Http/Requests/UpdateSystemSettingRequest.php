<?php

namespace App\Http\Requests;

use App\Enums\SettingValueType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSystemSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scope_type' => 'sometimes|nullable|string|max:255|required_with:scope_id',
            'scope_id' => 'sometimes|nullable|integer|required_with:scope_type',
            'key' => 'sometimes|string|max:255',
            'value' => 'sometimes|nullable',
            'type' => ['sometimes', 'sometimes', Rule::enum(SettingValueType::class)],
            'group' => 'sometimes|nullable|string|max:100',
            'description' => 'sometimes|nullable|string',
            'is_public' => 'sometimes|boolean',
        ];
    }
}
