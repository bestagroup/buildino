<?php

namespace App\Services\Wallet;

use App\Enums\FinancialAccountType;
use App\Models\Building;
use App\Models\BuildingWalletAccountingProfile;
use App\Models\FinancialAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BuildingWalletAccountingProfileService
{
    public function forBuilding(
        Building $building
    ): BuildingWalletAccountingProfile {
        $existing = BuildingWalletAccountingProfile::query()
            ->where(
                'building_id',
                $building->getKey()
            )
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use (
            $building
        ): BuildingWalletAccountingProfile {
            $currency = strtoupper(
                $building->currency ?: 'IRR'
            );

            $walletAsset = $this->systemAccount(
                $building,
                'SYS_WALLET_ASSET',
                'Building Wallet Asset',
                FinancialAccountType::Cash,
                $currency
            );

            $chargeIncome = $this->systemAccount(
                $building,
                'SYS_CHARGE_COLLECTION',
                'Charge Collection Income',
                FinancialAccountType::Income,
                $currency
            );

            $facilityIncome = $this->systemAccount(
                $building,
                'SYS_FACILITY_INCOME',
                'Facility Income',
                FinancialAccountType::Income,
                $currency
            );

            $billExpense = $this->systemAccount(
                $building,
                'SYS_BILL_EXPENSE',
                'Building Bill Expense',
                FinancialAccountType::Expense,
                $currency
            );

            $bankClearing = $this->systemAccount(
                $building,
                'SYS_BANK_CLEARING',
                'Building Bank Clearing',
                FinancialAccountType::Bank,
                $currency
            );

            return BuildingWalletAccountingProfile::query()
                ->firstOrCreate(
                    [
                        'building_id' =>
                            $building->getKey(),
                    ],
                    [
                        'wallet_asset_account_id' =>
                            $walletAsset->getKey(),
                        'charge_collection_credit_account_id' =>
                            $chargeIncome->getKey(),
                        'facility_income_account_id' =>
                            $facilityIncome->getKey(),
                        'bill_expense_account_id' =>
                            $billExpense->getKey(),
                        'bank_clearing_account_id' =>
                            $bankClearing->getKey(),
                        'is_active' => true,
                    ]
                )
                ->refresh();
        }, 3);
    }

    public function update(
        Building $building,
        array $data
    ): BuildingWalletAccountingProfile {
        $profile = $this->forBuilding(
            $building
        );

        foreach ([
            'wallet_asset_account_id',
            'charge_collection_credit_account_id',
            'facility_income_account_id',
            'bill_expense_account_id',
            'bank_clearing_account_id',
        ] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $this->assertAccountBelongsToBuilding(
                $building,
                (int) $data[$field]
            );
        }

        $profile->update($data);

        return $profile->refresh();
    }

    private function systemAccount(
        Building $building,
        string $code,
        string $title,
        FinancialAccountType $type,
        string $currency
    ): FinancialAccount {
        $account = FinancialAccount::query()
            ->firstOrCreate(
                [
                    'building_id' =>
                        $building->getKey(),
                    'code' => $code,
                ],
                [
                    'title' => $title,
                    'type' => $type,
                    'currency' => $currency,
                    'is_active' => true,
                ]
            );

        if (
            ! $account->is_active
            || strtoupper($account->currency)
                !== $currency
        ) {
            throw ValidationException::withMessages([
                'financial_account' =>
                    "System financial account {$code} is inactive or has an incompatible currency.",
            ]);
        }

        return $account;
    }

    private function assertAccountBelongsToBuilding(
        Building $building,
        int $accountId
    ): void {
        $valid = FinancialAccount::query()
            ->whereKey($accountId)
            ->where(
                'building_id',
                $building->getKey()
            )
            ->where('is_active', true)
            ->where(
                'currency',
                strtoupper(
                    $building->currency ?: 'IRR'
                )
            )
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages([
                'financial_account' =>
                    'Accounting profile accounts must be active accounts of the same building and currency.',
            ]);
        }
    }
}
