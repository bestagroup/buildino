<?php

namespace App\Models;

use App\Enums\FacilityType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BuildingFacility extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'building_facilities';

    protected $fillable = [
        'building_id',
        'title',
        'code',
        'description',
        'image',
        'type',
        'capacity',
        'default_price',
        'requires_payment',
        'requires_approval',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'default_price' => 'integer',
            'requires_payment' => 'boolean',
            'requires_approval' => 'boolean',
            'is_active' => 'boolean',
            'type' => FacilityType::class,
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function facilitySchedules(): HasMany
    {
        return $this->hasMany(FacilitySchedule::class, 'building_facility_id');
    }

    public function facilityReservationRules(): HasMany
    {
        return $this->hasMany(FacilityReservationRule::class, 'building_facility_id');
    }

    public function facilityBlackouts(): HasMany
    {
        return $this->hasMany(FacilityBlackout::class, 'building_facility_id');
    }

    public function facilityReservations(): HasMany
    {
        return $this->hasMany(FacilityReservation::class, 'building_facility_id');
    }
}
