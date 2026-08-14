<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildingServiceFinancialSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'building_id',
        'platform_commission_bps',
        'allow_user_wallet',
        'allow_unit_wallet',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'platform_commission_bps' => 'integer',
            'allow_user_wallet' => 'boolean',
            'allow_unit_wallet' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }
}
