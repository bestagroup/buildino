<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildingWalletAccountingProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'building_id',
        'wallet_asset_account_id',
        'charge_collection_credit_account_id',
        'facility_income_account_id',
        'bill_expense_account_id',
        'bank_clearing_account_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function walletAssetAccount(): BelongsTo
    {
        return $this->belongsTo(
            FinancialAccount::class,
            'wallet_asset_account_id'
        );
    }

    public function chargeCollectionCreditAccount(): BelongsTo
    {
        return $this->belongsTo(
            FinancialAccount::class,
            'charge_collection_credit_account_id'
        );
    }

    public function facilityIncomeAccount(): BelongsTo
    {
        return $this->belongsTo(
            FinancialAccount::class,
            'facility_income_account_id'
        );
    }

    public function billExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(
            FinancialAccount::class,
            'bill_expense_account_id'
        );
    }

    public function bankClearingAccount(): BelongsTo
    {
        return $this->belongsTo(
            FinancialAccount::class,
            'bank_clearing_account_id'
        );
    }
}
