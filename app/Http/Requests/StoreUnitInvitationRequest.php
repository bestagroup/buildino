<?php

namespace App\Http\Requests;

use App\Enums\InvitationChannel;
use App\Enums\OccupancyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_ids' => [
                'required',
                'array',
                'min:1',
                'max:50',
            ],

            'user_ids.*' => [
                'integer',
                'distinct',
                'exists:users,id',
            ],

            'relation_type' => [
                'required',
                Rule::enum(OccupancyType::class),
            ],

            'channel' => [
                'required',
                Rule::enum(InvitationChannel::class),
            ],

            'mobile' => [
                'prohibited',
            ],

            'email' => [
                'prohibited',
            ],

            'message' => [
                'required',
                'string',
                'max:1600',
            ],

            'expires_in_hours' => [
                'sometimes',
                'integer',
                'min:1',
                'max:720',
            ],
        ];
    }
}
