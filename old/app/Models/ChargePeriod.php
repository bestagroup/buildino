<?php

namespace App\Models;

use App\Enums\ChargePeriodStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChargePeriod extends Model
{
    use HasFactory;

    protected $table = 'charge_periods';

    protected $fillable = [
        'building_id',
        'title',
        'period_start',
        'period_end',
        'due_date',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'due_date' => 'date',
            'status' => ChargePeriodStatus::class,
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function chargeCalculations(): HasMany
    {
        return $this->hasMany(ChargeCalculation::class, 'charge_period_id');
    }

    public function unitInvoices(): HasMany
    {
        return $this->hasMany(UnitInvoice::class, 'charge_period_id');
    }
}
