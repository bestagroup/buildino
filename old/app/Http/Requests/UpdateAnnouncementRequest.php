<?php

namespace App\Http\Requests;

use App\Enums\AnnouncementPriority;
use App\Enums\AnnouncementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'type' => ['sometimes', 'sometimes', Rule::enum(AnnouncementType::class)],
            'priority' => ['sometimes', 'sometimes', Rule::enum(AnnouncementPriority::class)],
            'starts_at' => 'sometimes|nullable|date',
            'expires_at' => 'sometimes|nullable|date|after:starts_at',
            'published_at' => 'sometimes|nullable|date',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
