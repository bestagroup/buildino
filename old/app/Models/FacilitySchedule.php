<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacilitySchedule extends Model
{
    use HasFactory;

    protected $table = 'facility_schedules';

    protected $fillable = [
        'building_facility_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function buildingFacility(): BelongsTo
    {
        return $this->belongsTo(BuildingFacility::class, 'building_facility_id');
    }

    public function facilityTimeSlots(): HasMany
    {
        return $this->hasMany(FacilityTimeSlot::class, 'facility_schedule_id');
    }
}
