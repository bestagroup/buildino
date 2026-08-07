<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'floor_id',
        'unit_number',
        'title',
        'area',
        'bedrooms',
        'usage_type',
        'ownership_status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'area' => 'integer',
            'bedrooms' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function parkingSpaces(): HasMany
    {
        return $this->hasMany(ParkingSpace::class);
    }

    public function storageUnits(): HasMany
    {
        return $this->hasMany(StorageUnit::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(UnitDocument::class);
    }

    public function activeParkingSpaces(): HasMany
    {
        return $this->hasMany(ParkingSpace::class)
            ->where('is_active', true);
    }

    public function activeStorageUnits(): HasMany
    {
        return $this->hasMany(StorageUnit::class)
            ->where('is_active', true);
    }

    public function activeDocuments(): HasMany
    {
        return $this->hasMany(UnitDocument::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeResidential($query)
    {
        return $query->where('usage_type', 'residential');
    }

    public function scopeCommercial($query)
    {
        return $query->where('usage_type', 'commercial');
    }

    public function scopeOffice($query)
    {
        return $query->where('usage_type', 'office');
    }

    public function scopeVacant($query)
    {
        return $query->where('ownership_status', 'vacant');
    }

    public function scopeOwnerOccupied($query)
    {
        return $query->where('ownership_status', 'owner_occupied');
    }

    public function scopeTenantOccupied($query)
    {
        return $query->where('ownership_status', 'tenant_occupied');
    }
    public function residents(): HasMany
    {
        return $this->hasMany(UnitResident::class);
    }
    public function residentHistories(): HasMany
    {
        return $this->hasMany(ResidentHistory::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(UnitInvitation::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(UnitGuest::class);
    }
}
