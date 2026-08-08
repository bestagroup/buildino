<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacilityBlackout extends Model
{
    use HasFactory;

    protected $table = 'facility_blackouts';

    protected $fillable = [
        'building_facility_id',
        'starts_at',
        'ends_at',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function buildingFacility(): BelongsTo
    {
        return $this->belongsTo(BuildingFacility::class, 'building_facility_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
