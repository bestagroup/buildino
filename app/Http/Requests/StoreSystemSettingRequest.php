<?php

namespace App\Http\Requests;

use App\Enums\SettingValueType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSystemSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scope_type' => 'nullable|string|max:255|required_with:scope_id',
            'scope_id' => 'nullable|integer|required_with:scope_type',
            'key' => 'required|string|max:255',
            'value' => 'nullable',
            'type' => ['sometimes', Rule::enum(SettingValueType::class)],
            'group' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'is_public' => 'sometimes|boolean',
        ];
    }
}
