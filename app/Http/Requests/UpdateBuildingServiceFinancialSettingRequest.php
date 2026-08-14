<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBuildingServiceFinancialSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform_commission_bps' => [
                'sometimes',
                'integer',
                'min:0',
                'max:10000',
            ],
            'allow_user_wallet' => [
                'sometimes',
                'boolean',
            ],
            'allow_unit_wallet' => [
                'sometimes',
                'boolean',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
