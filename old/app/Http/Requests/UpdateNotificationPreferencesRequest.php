<?php

namespace App\Http\Requests;

use App\Enums\NotificationChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'preferences' => ['required', 'array', 'min:1', 'max:100'],
            'preferences.*.notification_type' => ['required', 'string', 'max:150'],
            'preferences.*.channel' => ['required', Rule::enum(NotificationChannel::class)],
            'preferences.*.is_enabled' => ['required', 'boolean'],
        ];
    }
}
