<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChargeExpenseAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'charge_period_id',
        'building_expense_id',
        'unit_id',
        'building_expense_allocation_rule_id',
        'base_value',
        'allocated_amount',
        'calculation_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'base_value' => 'decimal:4',
            'allocated_amount' => 'integer',
            'calculation_snapshot' => 'array',
        ];
    }

    public function chargePeriod(): BelongsTo
    {
        return $this->belongsTo(ChargePeriod::class);
    }

    public function buildingExpense(): BelongsTo
    {
        return $this->belongsTo(BuildingExpense::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(
            BuildingExpenseAllocationRule::class,
            'building_expense_allocation_rule_id'
        );
    }
}
