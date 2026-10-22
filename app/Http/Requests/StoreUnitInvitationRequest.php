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

    protected function prepareForValidation(): void
    {
        /*
         * The management invitation flow is SMS-first. Existing API clients
         * may still provide user_ids/channel explicitly, but a direct mobile
         * invitation defaults to SMS and the message body is server-owned.
         */
        if (
            $this->filled('mobile')
            && ! $this->filled('channel')
        ) {
            $this->merge([
                'channel' => InvitationChannel::Sms->value,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'user_ids' => [
                'nullable',
                'array',
                'min:1',
                'max:50',
                'required_without:mobile',
                Rule::prohibitedIf(
                    fn (): bool => $this->filled('mobile')
                ),
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
                'nullable',
                'string',
                'regex:/^09\d{9}$/',
                'required_without:user_ids',
                Rule::prohibitedIf(
                    fn (): bool => $this->filled('user_ids')
                ),
            ],

            /*
             * Email invitation for existing users remains API-compatible.
             * Direct public-registration invitations are SMS-only.
             */
            'email' => [
                'prohibited',
            ],

            /*
             * Kept nullable for backward API compatibility. The service
             * deliberately ignores custom text and stores the configured
             * fixed invitation message.
             */
            'message' => [
                'nullable',
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

    public function messages(): array
    {
        return [
            'user_ids.required_without' =>
                'حداقل یک کاربر یا شماره موبایل دریافت‌کننده را مشخص کنید.',
            'mobile.required_without' =>
                'شماره موبایل دریافت‌کننده دعوت را وارد کنید.',
            'mobile.regex' =>
                'شماره موبایل باید با 09 شروع شود و 11 رقم باشد.',
        ];
    }
}
