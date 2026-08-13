<?php

namespace App\Models;

use App\Enums\ChargePolicyMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildingChargePolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'building_id',
        'mode',
        'fixed_monthly_amount',
        'auto_collect',
        'allow_partial',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'mode' => ChargePolicyMode::class,
            'fixed_monthly_amount' => 'integer',
            'auto_collect' => 'boolean',
            'allow_partial' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }
}
