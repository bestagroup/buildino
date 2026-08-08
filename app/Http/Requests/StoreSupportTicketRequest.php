<?php

namespace App\Http\Requests;

use App\Enums\SupportTicketStatus;
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
            'user_id' => 'required|integer|exists:users,id',
            'building_id' => 'nullable|integer|exists:buildings,id',
            'unit_id' => 'nullable|integer|exists:units,id',
            'support_category_id' => 'nullable|integer|exists:support_categories,id',
            'ticket_number' => 'required|string|max:100|unique:support_tickets,ticket_number',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => ['sometimes', Rule::enum(SupportPriority::class)],
            'status' => ['sometimes', Rule::enum(SupportTicketStatus::class)],
            'assigned_to' => 'nullable|integer|exists:users,id',
        ];
    }
}
