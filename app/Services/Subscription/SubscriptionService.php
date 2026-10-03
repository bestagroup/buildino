<?php

namespace App\Services\Subscription;

use App\Enums\SubscriptionStatus;
use App\Models\Building;
use App\Models\BuildingFeature;
use App\Models\BuildingSubscription;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SubscriptionService
{
    public function current(Building $building): ?BuildingSubscription
    {
        return $building->buildingSubscriptions()
            ->with('plan.features')
            ->where('starts_at', '<=', now())
            ->latest('starts_at')
            ->latest('id')
            ->first();
    }

    public function canAccess(Building $building): bool
    {
        $subscription = $this->current($building);

        if (! $subscription) {
            return false;
        }

        if (in_array($subscription->status, [
            SubscriptionStatus::Pending,
            SubscriptionStatus::Cancelled,
            SubscriptionStatus::Suspended,
        ], true)) {
            return false;
        }

        if ($subscription->expires_at === null || $subscription->expires_at->isFuture()) {
            return true;
        }

        return $subscription->grace_ends_at?->isFuture() === true;
    }

    public function ensureTrial(Building $building, ?User $createdBy = null): BuildingSubscription
    {
        $current = $this->current($building);

        if ($current && $this->canAccess($building)) {
            return $current;
        }

        $trial = Plan::query()->firstOrCreate(
            ['code' => 'trial'],
            [
                'title' => 'آزمایشی',
                'description' => 'پلن آزمایشی پیش‌فرض؛ محدودیت‌ها و قیمت‌گذاری از پنل مدیریت قابل تنظیم است.',
                'price' => 0,
                'duration_days' => 30,
                'is_active' => true,
            ]
        );

        return $this->activate(
            $building,
            $trial,
            $createdBy,
            durationDays: $trial->duration_days ?? 30,
            graceDays: 7
        );
    }

    public function activate(
        Building $building,
        Plan $plan,
        ?User $createdBy = null,
        ?int $durationDays = null,
        int $graceDays = 7,
        ?array $limits = null
    ): BuildingSubscription {
        if (! $plan->is_active) {
            throw new RuntimeException('The selected subscription plan is inactive.');
        }

        return DB::transaction(function () use (
            $building,
            $plan,
            $createdBy,
            $durationDays,
            $graceDays,
            $limits
        ): BuildingSubscription {
            Building::query()->whereKey($building->getKey())->lockForUpdate()->firstOrFail();

            $previous = $building->buildingSubscriptions()
                ->latest('starts_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $building->buildingSubscriptions()
                ->whereIn('status', [
                    SubscriptionStatus::Active->value,
                    SubscriptionStatus::Pending->value,
                ])
                ->update(['status' => SubscriptionStatus::Expired->value]);

            $startsAt = now();
            $days = $durationDays ?? $plan->duration_days;
            $expiresAt = $days ? $startsAt->copy()->addDays($days) : null;
            $graceEndsAt = $expiresAt
                ? $expiresAt->copy()->addDays(max(0, $graceDays))
                : null;

            return $building->buildingSubscriptions()->create([
                'plan_id' => $plan->getKey(),
                'renewed_from_id' => $previous?->getKey(),
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'grace_ends_at' => $graceEndsAt,
                'status' => SubscriptionStatus::Active,
                'limits' => $limits,
                'created_by' => $createdBy?->getKey(),
            ]);
        }, 3);
    }

    public function suspend(BuildingSubscription $subscription): BuildingSubscription
    {
        $subscription->forceFill([
            'status' => SubscriptionStatus::Suspended,
            'suspended_at' => now(),
        ])->save();

        return $subscription->refresh();
    }

    public function resume(BuildingSubscription $subscription): BuildingSubscription
    {
        if ($subscription->cancelled_at !== null) {
            throw new RuntimeException('A cancelled subscription cannot be resumed.');
        }

        $subscription->forceFill([
            'status' => SubscriptionStatus::Active,
            'suspended_at' => null,
        ])->save();

        return $subscription->refresh();
    }

    public function featureValue(Building $building, string $code): mixed
    {
        $feature = Feature::query()->where('code', $code)->first();

        if (! $feature) {
            return null;
        }

        $override = BuildingFeature::query()
            ->where('building_id', $building->getKey())
            ->where('feature_id', $feature->getKey())
            ->first();

        if ($override) {
            return $override->is_enabled ? $override->value : false;
        }

        $subscription = $this->current($building);

        if (! $subscription?->plan) {
            return null;
        }

        return $subscription->plan
            ->planFeatures()
            ->where('feature_id', $feature->getKey())
            ->value('value');
    }
}
