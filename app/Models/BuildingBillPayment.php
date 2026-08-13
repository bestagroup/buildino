<?php

namespace App\Models;

use App\Enums\BuildingBillPaymentStatus;
use App\Enums\BuildingBillType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildingBillPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'building_id',
        'wallet_id',
        'bill_type',
        'bill_identifier',
        'payment_identifier',
        'amount',
        'status',
        'requested_by',
        'completed_by',
        'wallet_transfer_id',
        'provider',
        'provider_reference',
        'provider_payload',
        'failure_reason',
        'completed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'bill_type' => BuildingBillType::class,
            'status' => BuildingBillPaymentStatus::class,
            'provider_payload' => 'array',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'requested_by'
        );
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'completed_by'
        );
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(
            WalletTransfer::class,
            'wallet_transfer_id'
        );
    }
}
