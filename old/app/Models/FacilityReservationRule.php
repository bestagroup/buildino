<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacilityReservationRule extends Model
{
    use HasFactory;

    protected $table = 'facility_reservation_rules';

    protected $fillable = [
        'building_facility_id',
        'min_duration_minutes',
        'max_duration_minutes',
        'min_advance_minutes',
        'max_advance_days',
        'max_reservations_per_day',
        'max_reservations_per_week',
        'max_reservations_per_month',
        'max_reservation_per_unit',
        'cancel_before_minutes',
        'cancellation_fee',
        'refund_percentage',
        'allow_guest',
        'auto_confirm',
    ];

    protected function casts(): array
    {
        return [
            'min_duration_minutes' => 'integer',
            'max_duration_minutes' => 'integer',
            'min_advance_minutes' => 'integer',
            'max_advance_days' => 'integer',
            'max_reservations_per_day' => 'integer',
            'max_reservations_per_week' => 'integer',
            'max_reservations_per_month' => 'integer',
            'max_reservation_per_unit' => 'integer',
            'cancel_before_minutes' => 'integer',
            'cancellation_fee' => 'integer',
            'refund_percentage' => 'integer',
            'allow_guest' => 'boolean',
            'auto_confirm' => 'boolean',
        ];
    }

    public function buildingFacility(): BelongsTo
    {
        return $this->belongsTo(BuildingFacility::class, 'building_facility_id');
    }
}
