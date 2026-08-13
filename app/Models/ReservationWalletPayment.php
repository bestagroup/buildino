<?php

namespace App\Models;

use App\Enums\FacilityWalletPayerSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationWalletPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'facility_reservation_id',
        'wallet_transfer_id',
        'source_wallet_id',
        'building_wallet_id',
        'payer_source',
        'amount',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'payer_source' => FacilityWalletPayerSource::class,
            'amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(FacilityReservation::class);
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(
            WalletTransfer::class,
            'wallet_transfer_id'
        );
    }

    public function sourceWallet(): BelongsTo
    {
        return $this->belongsTo(
            Wallet::class,
            'source_wallet_id'
        );
    }

    public function buildingWallet(): BelongsTo
    {
        return $this->belongsTo(
            Wallet::class,
            'building_wallet_id'
        );
    }
}
