<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChargeCalculation extends Model
{
    use HasFactory;

    protected $table = 'charge_calculations';

    protected $fillable = [
        'charge_period_id',
        'unit_id',
        'charge_formula_id',
        'base_value',
        'calculated_amount',
        'calculation_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'base_value' => 'decimal:4',
            'calculated_amount' => 'integer',
            'calculation_snapshot' => 'array',
        ];
    }

    public function chargePeriod(): BelongsTo
    {
        return $this->belongsTo(ChargePeriod::class, 'charge_period_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function chargeFormula(): BelongsTo
    {
        return $this->belongsTo(ChargeFormula::class, 'charge_formula_id');
    }
}
