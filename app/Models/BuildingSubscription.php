<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildingSubscription extends Model
{
    use HasFactory;

    protected $table = 'building_subscriptions';

    protected $fillable = [
        'building_id',
        'plan_id',
        'starts_at',
        'expires_at',
        'grace_ends_at',
        'renewed_at',
        'suspended_at',
        'cancelled_at',
        'status',
        'limits',
        'metadata',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'renewed_at' => 'datetime',
            'suspended_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'limits' => 'array',
            'metadata' => 'array',
            'status' => SubscriptionStatus::class,
        ];
    }

    public function scopeUsable(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();

        return $query
            ->where('status', SubscriptionStatus::Active->value)
            ->where('starts_at', '<=', $at)
            ->where(function (Builder $query) use ($at): void {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $at)
                    ->orWhere('grace_ends_at', '>', $at);
            });
    }

    public function isUsable(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        if ($this->status !== SubscriptionStatus::Active) {
            return false;
        }

        if ($this->starts_at !== null && $this->starts_at->isAfter($at)) {
            return false;
        }

        if ($this->expires_at === null || $this->expires_at->isAfter($at)) {
            return true;
        }

        return $this->grace_ends_at !== null
            && $this->grace_ends_at->isAfter($at);
    }

    public function isInGracePeriod(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->status === SubscriptionStatus::Active
            && $this->expires_at !== null
            && ! $this->expires_at->isAfter($at)
            && $this->grace_ends_at !== null
            && $this->grace_ends_at->isAfter($at);
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
