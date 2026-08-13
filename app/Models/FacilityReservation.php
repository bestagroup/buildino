<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use App\Enums\ReservationApprovalType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FacilityReservation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'facility_reservations';

    protected $fillable = [
        'uuid',
        'building_facility_id',
        'facility_time_slot_id',
        'unit_id',
        'user_id',
        'reservation_date',
        'start_time',
        'end_time',
        'price',
        'discount_amount',
        'final_amount',
        'rule_snapshot',
        'status',
        'approval_type',
        'description',
        'approved_by',
        'approved_at',
        'confirmed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
            'price' => 'integer',
            'discount_amount' => 'integer',
            'final_amount' => 'integer',
            'rule_snapshot' => 'array',
            'approved_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'expires_at' => 'datetime',
            'status' => ReservationStatus::class,
            'approval_type' => ReservationApprovalType::class,
        ];
    }

    public function buildingFacility(): BelongsTo
    {
        return $this->belongsTo(BuildingFacility::class, 'building_facility_id');
    }

    public function facilityTimeSlot(): BelongsTo
    {
        return $this->belongsTo(FacilityTimeSlot::class, 'facility_time_slot_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }


    public function walletPayment(): HasOne
    {
        return $this->hasOne(
            ReservationWalletPayment::class,
            'facility_reservation_id'
        );
    }

    public function reservationCancellations(): HasMany
    {
        return $this->hasMany(ReservationCancellation::class, 'facility_reservation_id');
    }

    public function fileRelations(): MorphMany
    {
        return $this->morphMany(FileRelation::class, 'related');
    }
}
