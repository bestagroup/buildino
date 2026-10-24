<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcceptUnitInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => [
                'required',
                'string',
                'min:'.min(8, (int) config('unit_invitations.code_digits', 8)),
                'max:255',
            ],
        ];
    }
}
