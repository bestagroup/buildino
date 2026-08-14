<?php

namespace App\Http\Requests;

use App\Enums\SupportPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_id' => ['sometimes', 'nullable', 'integer', 'exists:buildings,id'],
            'unit_id' => ['sometimes', 'nullable', 'integer', 'exists:units,id'],
            'support_category_id' => ['sometimes', 'nullable', 'integer', 'exists:support_categories,id'],
            'subject' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:20000'],
            'priority' => ['sometimes', Rule::enum(SupportPriority::class)],
        ];
    }
}
