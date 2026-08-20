<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyTransactionAllocation extends Model
{
    protected $fillable = [
        'spend_transaction_id',
        'earn_transaction_id',
        'points',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
        ];
    }

    public function spendTransaction(): BelongsTo
    {
        return $this->belongsTo(
            LoyaltyTransaction::class,
            'spend_transaction_id'
        );
    }

    public function earnTransaction(): BelongsTo
    {
        return $this->belongsTo(
            LoyaltyTransaction::class,
            'earn_transaction_id'
        );
    }
}
