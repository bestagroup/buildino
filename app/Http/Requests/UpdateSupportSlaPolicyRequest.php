<?php

namespace App\Http\Requests;

use App\Enums\SupportPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupportSlaPolicyRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'support_category_id' => ['sometimes', 'nullable', 'integer', 'exists:support_categories,id'],
            'priority' => ['sometimes', Rule::enum(SupportPriority::class)],
            'first_response_minutes' => ['sometimes', 'integer', 'min:1', 'max:525600'],
            'resolution_minutes' => ['sometimes', 'integer', 'min:1', 'max:525600'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
