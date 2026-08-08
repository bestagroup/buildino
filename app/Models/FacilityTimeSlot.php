<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacilityTimeSlot extends Model
{
    use HasFactory;

    protected $table = 'facility_time_slots';

    protected $fillable = [
        'facility_schedule_id',
        'start_time',
        'end_time',
        'capacity',
        'price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function facilitySchedule(): BelongsTo
    {
        return $this->belongsTo(FacilitySchedule::class, 'facility_schedule_id');
    }

    public function facilityReservations(): HasMany
    {
        return $this->hasMany(FacilityReservation::class, 'facility_time_slot_id');
    }
}
