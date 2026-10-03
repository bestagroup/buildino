<?php

namespace App\Services\Subscription;

use App\Enums\SubscriptionStatus;
use App\Models\Building;
use App\Models\BuildingFeature;
use App\Models\BuildingSubscription;
use App\Models\Feature;
use Carbon\CarbonInterface;

final class SubscriptionEntitlementService
{
    public function activeSubscription(
        Building $building,
        ?CarbonInterface $at = null
    ): ?BuildingSubscription {
        $at ??= now();

        return $building->buildingSubscriptions()
            ->with('plan.features')
            ->where('starts_at', '<=', $at)
            ->where('status', SubscriptionStatus::Active->value)
            ->where(function ($query) use ($at): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $at)
                    ->orWhere('grace_ends_at', '>', $at);
            })
            ->latest('starts_at')
            ->first();
    }

    public function isUsable(
        Building $building,
        ?CarbonInterface $at = null
    ): bool {
        return $this->activeSubscription($building, $at) !== null;
    }

    public function featureEnabled(
        Building $building,
        string $featureCode
    ): bool {
        $subscription = $this->activeSubscription($building);

        if (! $subscription) {
            return false;
        }

        $feature = Feature::query()
            ->where('code', $featureCode)
            ->first();

        if (! $feature) {
            return false;
        }

        $override = BuildingFeature::query()
            ->where('building_id', $building->getKey())
            ->where('feature_id', $feature->getKey())
            ->first();

        if ($override) {
            return $override->is_enabled
                && $this->truthy($override->value);
        }

        $planFeature = $subscription->plan
            ?->features
            ?->firstWhere('id', $feature->getKey());

        if (! $planFeature) {
            return false;
        }

        return $this->truthy($planFeature->pivot->value);
    }

    public function limit(
        Building $building,
        string $featureCode
    ): ?int {
        $subscription = $this->activeSubscription($building);

        if (! $subscription) {
            return null;
        }

        if (
            isset($subscription->limits[$featureCode])
            && is_numeric($subscription->limits[$featureCode])
        ) {
            return (int) $subscription->limits[$featureCode];
        }

        $feature = Feature::query()
            ->where('code', $featureCode)
            ->first();

        if (! $feature) {
            return null;
        }

        $override = BuildingFeature::query()
            ->where('building_id', $building->getKey())
            ->where('feature_id', $feature->getKey())
            ->first();

        $value = $override?->value;

        if ($value === null && $subscription->plan) {
            $planFeature = $subscription->plan
                ->features
                ->firstWhere('id', $feature->getKey());

            $value = $planFeature?->pivot?->value;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function truthy(mixed $value): bool
    {
        if (is_array($value) && array_key_exists('enabled', $value)) {
            return (bool) $value['enabled'];
        }

        if (is_array($value) && count($value) === 1) {
            $value = array_values($value)[0];
        }

        return filter_var(
            $value,
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE
        ) ?? (bool) $value;
    }
}
