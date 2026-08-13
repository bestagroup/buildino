<?php

namespace App\Models;

use App\Enums\WalletTransferStatus;
use App\Enums\WalletTransferType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WalletTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'source_wallet_id',
        'destination_wallet_id',
        'amount',
        'currency',
        'type',
        'status',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'description',
        'created_by',
        'completed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'type' => WalletTransferType::class,
            'status' => WalletTransferStatus::class,
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function sourceWallet(): BelongsTo
    {
        return $this->belongsTo(
            Wallet::class,
            'source_wallet_id'
        );
    }

    public function destinationWallet(): BelongsTo
    {
        return $this->belongsTo(
            Wallet::class,
            'destination_wallet_id'
        );
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function entries(): HasMany
    {
        return $this->hasMany(WalletEntry::class);
    }
}
