<?php

namespace App\Models;

use App\Enums\WalletEntryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'wallet_transfer_id',
        'entry_type',
        'amount',
        'balance_after',
    ];

    protected function casts(): array
    {
        return [
            'entry_type' => WalletEntryType::class,
            'amount' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(
            WalletTransfer::class,
            'wallet_transfer_id'
        );
    }
}
