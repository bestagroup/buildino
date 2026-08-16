<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeetingMinuteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_id' => [
                'sometimes',
                'integer',
                Rule::in([
                    (int) $this->route(
                        'meeting_minute'
                    )?->building_id,
                ]),
            ],
            'title' => ['sometimes', 'string', 'max:255'],
            'meeting_at' => ['sometimes', 'date'],
            'meeting_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'content' => ['sometimes', 'nullable', 'string'],
            'created_by' => ['prohibited'],
        ];
    }
}
