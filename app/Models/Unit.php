<?php

namespace App\Models;

use App\Enums\UnitUsageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'units';

    protected $fillable = [
        'floor_id',
        'unit_number',
        'title',
        'area',
        'bedrooms',
        'usage_type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'area' => 'decimal:2',
            'bedrooms' => 'integer',
            'is_active' => 'boolean',
            'usage_type' => UnitUsageType::class,
        ];
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class, 'floor_id');
    }

    public function unitParkingAssignments(): HasMany
    {
        return $this->hasMany(UnitParkingAssignment::class, 'unit_id');
    }

    public function unitStorageAssignments(): HasMany
    {
        return $this->hasMany(UnitStorageAssignment::class, 'unit_id');
    }

    public function unitOwnerships(): HasMany
    {
        return $this->hasMany(UnitOwnership::class, 'unit_id');
    }

    public function unitOccupancies(): HasMany
    {
        return $this->hasMany(UnitOccupancy::class, 'unit_id');
    }

    public function unitInvitations(): HasMany
    {
        return $this->hasMany(UnitInvitation::class, 'unit_id');
    }

    public function guestVisits(): HasMany
    {
        return $this->hasMany(GuestVisit::class, 'unit_id');
    }

    public function chargeCalculations(): HasMany
    {
        return $this->hasMany(ChargeCalculation::class, 'unit_id');
    }

    public function unitInvoices(): HasMany
    {
        return $this->hasMany(UnitInvoice::class, 'unit_id');
    }

    public function facilityReservations(): HasMany
    {
        return $this->hasMany(FacilityReservation::class, 'unit_id');
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'unit_id');
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'unit_id');
    }

    public function parkingSpaces(): BelongsToMany
    {
        return $this->belongsToMany(ParkingSpace::class, 'unit_parking_assignments')
            ->withPivot(['starts_at', 'ends_at'])
            ->withTimestamps();
    }

    public function storageUnits(): BelongsToMany
    {
        return $this->belongsToMany(StorageUnit::class, 'unit_storage_assignments')
            ->withPivot(['starts_at', 'ends_at'])
            ->withTimestamps();
    }

    public function fileRelations(): MorphMany
    {
        return $this->morphMany(FileRelation::class, 'related');
    }
}
