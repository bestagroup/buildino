<?php

namespace App\Models;

use App\Enums\FinancialAccountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialAccount extends Model
{
    use HasFactory;

    protected $table = 'financial_accounts';

    protected $fillable = [
        'building_id',
        'code',
        'title',
        'type',
        'currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'type' => FinancialAccountType::class,
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function funds(): HasMany
    {
        return $this->hasMany(Fund::class, 'financial_account_id');
    }

    public function financialLedgerEntries(): HasMany
    {
        return $this->hasMany(FinancialLedgerEntry::class, 'financial_account_id');
    }

    public function financialReconciliations(): HasMany
    {
        return $this->hasMany(FinancialReconciliation::class, 'financial_account_id');
    }
}
