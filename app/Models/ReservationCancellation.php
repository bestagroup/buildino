<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationCancellation extends Model
{
    use HasFactory;

    protected $fillable = [
        'facility_reservation_id',
        'cancelled_by',
        'reason',
        'cancellation_fee',
        'refund_amount',
        'refund_status',
        'refund_payment_id',
        'refund_wallet_transfer_id',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'cancellation_fee' => 'integer',
            'refund_amount' => 'integer',
            'refund_status' => RefundStatus::class,
            'cancelled_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(
            FacilityReservation::class,
            'facility_reservation_id'
        );
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'cancelled_by'
        );
    }

    public function refundWalletTransfer(): BelongsTo
    {
        return $this->belongsTo(
            WalletTransfer::class,
            'refund_wallet_transfer_id'
        );
    }
}
