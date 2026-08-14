<?php

namespace App\Models;

use App\Enums\ParkingType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParkingSpace extends Model
{
    use HasFactory;

    protected $table = 'parking_spaces';

    protected $fillable = [
        'building_id',
        'parking_number',
        'title',
        'type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'type' => ParkingType::class,
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function unitParkingAssignments(): HasMany
    {
        return $this->hasMany(UnitParkingAssignment::class, 'parking_space_id');
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'unit_parking_assignments')
            ->withPivot(['starts_at', 'ends_at'])
            ->withTimestamps();
    }
}
