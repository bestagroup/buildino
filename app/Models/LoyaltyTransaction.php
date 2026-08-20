<?php

namespace App\Models;

use App\Enums\LoyaltyTransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LoyaltyTransaction extends Model
{
    use HasFactory;

    protected $table = 'loyalty_transactions';

    protected $fillable = [
        'loyalty_account_id',
        'loyalty_rule_id',
        'type',
        'points',
        'balance_after',
        'remaining_points',
        'idempotency_key',
        'metadata',
        'reversed_transaction_id',
        'reference',
        'description',
        'expires_at',
        'reference_type',
        'reference_id',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'balance_after' => 'integer',
            'remaining_points' => 'integer',
            'metadata' => 'array',
            'expires_at' => 'datetime',
            'type' => LoyaltyTransactionType::class,
        ];
    }

    public function loyaltyAccount(): BelongsTo
    {
        return $this->belongsTo(LoyaltyAccount::class, 'loyalty_account_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function loyaltyRule(): BelongsTo
    {
        return $this->belongsTo(LoyaltyRule::class, 'loyalty_rule_id');
    }

    public function reversedTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_transaction_id');
    }

    public function spendAllocations(): HasMany
    {
        return $this->hasMany(
            LoyaltyTransactionAllocation::class,
            'spend_transaction_id'
        );
    }

    public function earnAllocations(): HasMany
    {
        return $this->hasMany(
            LoyaltyTransactionAllocation::class,
            'earn_transaction_id'
        );
    }
}
