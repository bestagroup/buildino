<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fund extends Model
{
    use HasFactory;

    protected $table = 'funds';

    protected $fillable = [
        'building_id',
        'financial_account_id',
        'title',
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
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    public function buildingExpenses(): HasMany
    {
        return $this->hasMany(BuildingExpense::class, 'fund_id');
    }

    public function buildingIncomes(): HasMany
    {
        return $this->hasMany(BuildingIncome::class, 'fund_id');
    }
}
