<?php

namespace App\Models;

use App\Enums\ChargeCalculationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChargeFormula extends Model
{
    use HasFactory;

    protected $table = 'charge_formulas';

    protected $fillable = [
        'building_id',
        'title',
        'calculation_type',
        'configuration',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'is_active' => 'boolean',
            'calculation_type' => ChargeCalculationType::class,
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function chargeItems(): HasMany
    {
        return $this->hasMany(ChargeItem::class, 'charge_formula_id');
    }

    public function chargeCalculations(): HasMany
    {
        return $this->hasMany(ChargeCalculation::class, 'charge_formula_id');
    }
}
