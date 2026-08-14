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
            'relation_type' => [
                'required',
                Rule::enum(OccupancyType::class),
            ],

            'channel' => [
                'required',
                Rule::enum(InvitationChannel::class),
            ],

            'mobile' => [
                'nullable',
                'string',
                'max:20',
                Rule::requiredIf(
                    fn (): bool => $this->input('channel')
                        === InvitationChannel::Sms->value
                ),
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::requiredIf(
                    fn (): bool => $this->input('channel')
                        === InvitationChannel::Email->value
                ),
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
