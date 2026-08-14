<?php

namespace App\Models;

use App\Enums\UnitChargePayerSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitChargeSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'payer_source',
        'payer_user_id',
        'auto_collect',
        'allow_partial',
    ];

    protected function casts(): array
    {
        return [
            'payer_source' => UnitChargePayerSource::class,
            'auto_collect' => 'boolean',
            'allow_partial' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function payerUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'payer_user_id'
        );
    }
}
