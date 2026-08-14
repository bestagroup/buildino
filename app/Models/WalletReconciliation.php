<?php

namespace App\Models;

use App\Enums\WalletReconciliationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletReconciliation extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'wallet_id',
        'reconciled_at',
        'entry_balance',
        'stored_balance',
        'expected_locked_balance',
        'stored_locked_balance',
        'balance_difference',
        'lock_difference',
        'status',
        'details',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'reconciled_at' => 'datetime',
            'entry_balance' => 'integer',
            'stored_balance' => 'integer',
            'expected_locked_balance' => 'integer',
            'stored_locked_balance' => 'integer',
            'balance_difference' => 'integer',
            'lock_difference' => 'integer',
            'status' => WalletReconciliationStatus::class,
            'details' => 'array',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }
}
