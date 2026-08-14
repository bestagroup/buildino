<?php

namespace App\Models;

use App\Enums\FinancialCategoryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialCategory extends Model
{
    use HasFactory;

    protected $table = 'financial_categories';

    protected $fillable = [
        'building_id',
        'title',
        'type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'type' => FinancialCategoryType::class,
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function chargeItems(): HasMany
    {
        return $this->hasMany(ChargeItem::class, 'financial_category_id');
    }

    public function buildingExpenses(): HasMany
    {
        return $this->hasMany(BuildingExpense::class, 'financial_category_id');
    }

    public function buildingIncomes(): HasMany
    {
        return $this->hasMany(BuildingIncome::class, 'financial_category_id');
    }
}
