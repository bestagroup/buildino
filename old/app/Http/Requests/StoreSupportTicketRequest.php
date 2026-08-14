<?php

namespace App\Http\Requests;

use App\Enums\SupportPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_id' => ['nullable', 'integer', 'exists:buildings,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'support_category_id' => ['nullable', 'integer', 'exists:support_categories,id'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:20000'],
            'priority' => ['sometimes', Rule::enum(SupportPriority::class)],
        ];
    }
}
