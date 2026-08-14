<?php

namespace App\Models;

use App\Enums\LoyaltyClaimStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyRewardClaim extends Model
{
    use HasFactory;

    protected $table = 'loyalty_reward_claims';

    protected $fillable = [
        'loyalty_reward_id',
        'user_id',
        'claimed_at',
        'status',
        'processed_by',
        'processed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
            'processed_at' => 'datetime',
            'status' => LoyaltyClaimStatus::class,
        ];
    }

    public function loyaltyReward(): BelongsTo
    {
        return $this->belongsTo(LoyaltyReward::class, 'loyalty_reward_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
