<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingMinuteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_id' => ['required', 'integer', 'exists:buildings,id'],
            'title' => ['required', 'string', 'max:255'],
            'meeting_at' => ['required', 'date'],
            'meeting_number' => ['nullable', 'string', 'max:100'],
            'content' => ['nullable', 'string'],
            'created_by' => ['prohibited'],
        ];
    }
}
