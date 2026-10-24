<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUnitInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            /*
             * Recipient data already exists in Buildino. The form only sends
             * optional user ids; an empty list means all active users linked
             * to the selected unit.
             */
            'user_ids' => [
                Rule::requiredIf(fn (): bool => $this->has('occupancy')),
                'array',
                $this->has('occupancy') ? 'size:1' : 'max:50',
            ],

            'user_ids.*' => [
                'integer',
                'distinct',
                'exists:users,id',
            ],

            /*
             * These values are derived server-side from the selected users
             * and their existing unit relations. Accepting them from the
             * client would re-introduce duplicated / conflicting data.
             */
            'mobile' => ['prohibited'],
            'email' => ['prohibited'],
            'relation_type' => ['prohibited'],
            'channel' => ['prohibited'],
            'message' => ['prohibited'],

            'expires_in_hours' => [
                'sometimes',
                'integer',
                'min:1',
                'max:720',
            ],
            'occupancy' => ['sometimes', 'required', 'array:occupancy_type,starts_at,ends_at,is_primary,notes'],
            'occupancy.occupancy_type' => ['required_with:occupancy', Rule::in(['tenant', 'resident', 'family_member'])],
            'occupancy.starts_at' => ['required_with:occupancy', 'date'],
            'occupancy.ends_at' => ['nullable', 'date', 'after_or_equal:occupancy.starts_at'],
            'occupancy.is_primary' => ['sometimes', 'boolean'],
            'occupancy.notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
