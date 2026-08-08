<?php

namespace App\Http\Requests;

use App\Enums\SupportTicketStatus;
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
            'user_id' => 'sometimes|integer|exists:users,id',
            'building_id' => 'sometimes|nullable|integer|exists:buildings,id',
            'unit_id' => 'sometimes|nullable|integer|exists:units,id',
            'support_category_id' => 'sometimes|nullable|integer|exists:support_categories,id',
            'ticket_number' => ['sometimes', 'string', 'max:100', Rule::unique('support_tickets', 'ticket_number')->ignore($this->route('support_ticket')?->id ?? $this->route('support_ticket'))],
            'subject' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'priority' => ['sometimes', 'sometimes', Rule::enum(SupportPriority::class)],
            'status' => ['sometimes', 'sometimes', Rule::enum(SupportTicketStatus::class)],
            'assigned_to' => 'sometimes|nullable|integer|exists:users,id',
        ];
    }
}
