<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BuildingWalletAccountingProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'building_id' => $this->building_id,

            'wallet_asset_account_id' =>
                $this->wallet_asset_account_id,

            'charge_collection_credit_account_id' =>
                $this->charge_collection_credit_account_id,

            'facility_income_account_id' =>
                $this->facility_income_account_id,

            'bill_expense_account_id' =>
                $this->bill_expense_account_id,

            'bank_clearing_account_id' =>
                $this->bank_clearing_account_id,

            'is_active' => (bool) $this->is_active,

            'accounts' => [
                'wallet_asset' =>
                    $this->accountData(
                        $this->walletAssetAccount
                    ),

                'charge_collection_credit' =>
                    $this->accountData(
                        $this->chargeCollectionCreditAccount
                    ),

                'facility_income' =>
                    $this->accountData(
                        $this->facilityIncomeAccount
                    ),

                'bill_expense' =>
                    $this->accountData(
                        $this->billExpenseAccount
                    ),

                'bank_clearing' =>
                    $this->accountData(
                        $this->bankClearingAccount
                    ),
            ],

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }

    private function accountData(
        $account
    ): ?array {
        if (! $account) {
            return null;
        }

        return [
            'id' => $account->id,
            'code' => $account->code,
            'title' => $account->title,
            'type' => is_object($account->type)
                ? $account->type->value
                : $account->type,
            'currency' => $account->currency,
            'is_active' => (bool) $account->is_active,
        ];
    }
}
