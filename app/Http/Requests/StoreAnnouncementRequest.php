<?php

namespace App\Http\Requests;

use App\Enums\AnnouncementPriority;
use App\Enums\AnnouncementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => ['sometimes', Rule::enum(AnnouncementType::class)],
            'priority' => ['sometimes', Rule::enum(AnnouncementPriority::class)],
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
            'published_at' => 'nullable|date',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
