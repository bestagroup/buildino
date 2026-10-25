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
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            /*
             * Recipient data already exists in Buildino. The form only sends
             * recipient identifiers. Contact and relationship data are
             * resolved from existing unit links by the controller.
             */
            'user_ids' => [
                'nullable',
                'array',
                'max:50',
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
