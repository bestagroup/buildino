<?php

namespace App\Models;

use App\Enums\WalletPayoutStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderPayoutRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'provider_user_id',
        'wallet_id',
        'provider_bank_account_id',
        'amount',
        'fee_amount',
        'net_amount',
        'status',
        'requested_by',
        'approved_by',
        'paid_by',
        'wallet_transfer_id',
        'bank_reference',
        'rejection_reason',
        'approved_at',
        'paid_at',
        'rejected_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'fee_amount' => 'integer',
            'net_amount' => 'integer',
            'status' => WalletPayoutStatus::class,
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'provider_user_id'
        );
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(
            ProviderBankAccount::class,
            'provider_bank_account_id'
        );
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'requested_by'
        );
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'approved_by'
        );
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'paid_by'
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
