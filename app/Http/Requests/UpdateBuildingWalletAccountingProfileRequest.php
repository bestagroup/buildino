<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBuildingWalletAccountingProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'wallet_asset_account_id' => [
                'sometimes',
                'integer',
                'exists:financial_accounts,id',
            ],
            'charge_collection_credit_account_id' => [
                'sometimes',
                'integer',
                'exists:financial_accounts,id',
            ],
            'facility_income_account_id' => [
                'sometimes',
                'integer',
                'exists:financial_accounts,id',
            ],
            'bill_expense_account_id' => [
                'sometimes',
                'integer',
                'exists:financial_accounts,id',
            ],
            'bank_clearing_account_id' => [
                'sometimes',
                'integer',
                'exists:financial_accounts,id',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
