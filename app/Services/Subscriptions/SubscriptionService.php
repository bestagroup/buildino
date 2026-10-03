<?php

namespace App\Services\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Models\Building;
use App\Models\BuildingSubscription;
use App\Models\Plan;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SubscriptionService
{
    public function current(Building $building): ?BuildingSubscription
    {
        return $building
            ->buildingSubscriptions()
            ->with('plan.features')
            ->latest('starts_at')
            ->latest('id')
            ->first();
    }

    public function usable(Building $building): ?BuildingSubscription
    {
        return $building
            ->buildingSubscriptions()
            ->with('plan.features')
            ->usable()
            ->latest('starts_at')
            ->latest('id')
            ->first();
    }

    public function activate(
        Building $building,
        Plan $plan,
        User $actor,
        ?CarbonInterface $startsAt = null,
        ?int $durationDays = null,
        int $graceDays = 7,
        array $limits = [],
        array $metadata = []
    ): BuildingSubscription {
        if (! $plan->is_active) {
            throw new InvalidArgumentException('The selected subscription plan is inactive.');
        }

        $startsAt ??= now();
        $durationDays ??= $plan->duration_days;

        return DB::transaction(function () use (
            $building,
            $plan,
            $actor,
            $startsAt,
            $durationDays,
            $graceDays,
            $limits,
            $metadata
        ): BuildingSubscription {
            Building::query()
                ->whereKey($building->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $building->buildingSubscriptions()
                ->where('status', SubscriptionStatus::Active->value)
                ->update([
                    'status' => SubscriptionStatus::Expired->value,
                    'expires_at' => now(),
                    'grace_ends_at' => now(),
                ]);

            $expiresAt = $durationDays !== null
                ? $startsAt->copy()->addDays($durationDays)
                : null;

            $subscription = $building->buildingSubscriptions()->create([
                'plan_id' => $plan->getKey(),
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'grace_ends_at' => $expiresAt?->copy()->addDays(max(0, $graceDays)),
                'status' => SubscriptionStatus::Active,
                'limits' => $limits,
                'metadata' => $metadata,
                'created_by' => $actor->getKey(),
            ]);

            return $subscription->load('plan.features');
        });
    }

    public function renew(
        BuildingSubscription $subscription,
        User $actor,
        ?int $durationDays = null,
        int $graceDays = 7
    ): BuildingSubscription {
        return DB::transaction(function () use (
            $subscription,
            $actor,
            $durationDays,
            $graceDays
        ): BuildingSubscription {
            $subscription = BuildingSubscription::query()
                ->whereKey($subscription->getKey())
                ->lockForUpdate()
                ->with('plan.features')
                ->firstOrFail();

            if (in_array(
                $subscription->status,
                [SubscriptionStatus::Cancelled, SubscriptionStatus::Suspended],
                true
            )) {
                throw new InvalidArgumentException(
                    'A cancelled or suspended subscription must be activated explicitly.'
                );
            }

            $durationDays ??= $subscription->plan?->duration_days;

            if ($durationDays === null) {
                throw new InvalidArgumentException(
                    'A renewal duration is required for an unlimited plan.'
                );
            }

            $base = $subscription->expires_at !== null
                && $subscription->expires_at->isFuture()
                    ? $subscription->expires_at->copy()
                    : now();

            $expiresAt = $base->addDays($durationDays);

            $subscription->forceFill([
                'status' => SubscriptionStatus::Active,
                'expires_at' => $expiresAt,
                'grace_ends_at' => $expiresAt->copy()->addDays(max(0, $graceDays)),
                'renewed_at' => now(),
                'suspended_at' => null,
                'cancelled_at' => null,
                'metadata' => array_merge(
                    $subscription->metadata ?? [],
                    ['last_renewed_by' => $actor->getKey()]
                ),
            ])->save();

            return $subscription->refresh()->load('plan.features');
        });
    }

    public function suspend(
        BuildingSubscription $subscription,
        User $actor,
        ?string $reason = null
    ): BuildingSubscription {
        return $this->transition(
            $subscription,
            SubscriptionStatus::Suspended,
            $actor,
            'suspended_at',
            $reason
        );
    }

    public function cancel(
        BuildingSubscription $subscription,
        User $actor,
        ?string $reason = null
    ): BuildingSubscription {
        return $this->transition(
            $subscription,
            SubscriptionStatus::Cancelled,
            $actor,
            'cancelled_at',
            $reason
        );
    }

    public function effectiveFeatures(Building $building): array
    {
        $subscription = $this->usable($building);

        if (! $subscription) {
            return [];
        }

        $features = $subscription->plan
            ?->features
            ?->mapWithKeys(function ($feature): array {
                $value = $feature->pivot->value;

                if (is_string($value)) {
                    $decoded = json_decode($value, true);

                    if (json_last_error() === JSON_ERROR_NONE) {
                        $value = $decoded;
                    }
                }

                return [$feature->code => $value];
            })
            ->all() ?? [];

        $overrides = $building
            ->buildingFeatures()
            ->with('feature:id,code')
            ->get()
            ->mapWithKeys(
                fn ($feature): array => [
                    $feature->feature->code => $feature->is_enabled
                        ? $feature->value
                        : false,
                ]
            )
            ->all();

        return array_replace($features, $overrides);
    }

    private function transition(
        BuildingSubscription $subscription,
        SubscriptionStatus $status,
        User $actor,
        string $timestampColumn,
        ?string $reason
    ): BuildingSubscription {
        return DB::transaction(function () use (
            $subscription,
            $status,
            $actor,
            $timestampColumn,
            $reason
        ): BuildingSubscription {
            $subscription = BuildingSubscription::query()
                ->whereKey($subscription->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $subscription->forceFill([
                'status' => $status,
                $timestampColumn => now(),
                'metadata' => array_merge(
                    $subscription->metadata ?? [],
                    [
                        'last_transition_by' => $actor->getKey(),
                        'last_transition_reason' => $reason,
                    ]
                ),
            ])->save();

            return $subscription->refresh()->load('plan.features');
        });
    }
}
