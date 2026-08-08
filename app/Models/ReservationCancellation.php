<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReservationCancellation extends Model
{
    use HasFactory;

    protected $table = 'reservation_cancellations';

    protected $fillable = [
        'facility_reservation_id',
        'cancelled_by',
        'reason',
        'cancellation_fee',
        'refund_amount',
        'refund_status',
        'refund_payment_id',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'cancellation_fee' => 'integer',
            'refund_amount' => 'integer',
            'cancelled_at' => 'datetime',
            'refund_status' => RefundStatus::class,
        ];
    }

    public function facilityReservation(): BelongsTo
    {
        return $this->belongsTo(FacilityReservation::class, 'facility_reservation_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function refundPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'refund_payment_id');
    }
}
