<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyReward extends Model
{
    use HasFactory;

    protected $table = 'loyalty_rewards';

    protected $fillable = [
        'building_id',
        'title',
        'description',
        'required_points',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'required_points' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function loyaltyRewardClaims(): HasMany
    {
        return $this->hasMany(LoyaltyRewardClaim::class, 'loyalty_reward_id');
    }
}
