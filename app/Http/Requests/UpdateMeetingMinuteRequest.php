<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetingMinuteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_id' => ['sometimes', 'integer', 'exists:buildings,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'meeting_at' => ['sometimes', 'date'],
            'meeting_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'content' => ['sometimes', 'nullable', 'string'],
            'created_by' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
