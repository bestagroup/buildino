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
            /*
             * Recipient data already exists in Buildino. The form only sends
             * optional user ids; an empty list means all active users linked
             * to the selected unit.
             */
            'user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],

            'user_ids' => ['prohibited'],

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
