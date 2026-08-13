<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'owner_type',
        'owner_id',
        'currency',
        'balance',
        'locked_balance',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'locked_balance' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function entries(): HasMany
    {
        return $this->hasMany(WalletEntry::class);
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(
            WalletTransfer::class,
            'source_wallet_id'
        );
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(
            WalletTransfer::class,
            'destination_wallet_id'
        );
    }

    public function availableBalance(): int
    {
        return max(
            0,
            (int) $this->balance - (int) $this->locked_balance
        );
    }
}
