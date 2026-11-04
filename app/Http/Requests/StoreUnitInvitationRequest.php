<?php

namespace App\Http\Requests;

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
            'occupancy' => ['sometimes', 'required', 'array:occupancy_type,starts_at,ends_at,is_primary,notes,resident_count'],
            'occupancy.occupancy_type' => ['required_with:occupancy', 'in:tenant,resident,family_member'],
            'occupancy.starts_at' => ['required_with:occupancy', 'date'],
            'occupancy.ends_at' => ['nullable', 'date', 'after_or_equal:occupancy.starts_at'],
            'occupancy.is_primary' => ['sometimes', 'boolean'],
            'occupancy.resident_count' => ['sometimes', 'integer', 'min:0', 'max:255'],
            'occupancy.notes' => ['nullable', 'string', 'max:5000'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            /*
             * Recipient data already exists in Buildino. The form only sends
             * recipient identifiers. Contact and relationship data are
             * resolved from existing unit links by the controller.
             */
            'user_ids' => [
                'required_with:occupancy',
                'nullable',
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
        ];
    }
}
