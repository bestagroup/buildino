<?php

namespace App\Services\Subscription;

use App\Models\Building;
use App\Models\Plan;
use App\Models\User;
use RuntimeException;

final class TrialSubscriptionProvisioner
{
    public function __construct(
        private readonly SubscriptionLifecycleService $lifecycle
    ) {
    }

    public function provision(
        Building $building,
        ?User $actor = null
    ): void {
        if ($building->buildingSubscriptions()->exists()) {
            return;
        }

        $plan = Plan::query()
            ->where('code', 'trial')
            ->where('is_active', true)
            ->first();

        if (! $plan) {
            throw new RuntimeException(
                'Trial subscription plan is not configured. Run SubscriptionCatalogSeeder.'
            );
        }

        $this->lifecycle->create(
            $building,
            $plan,
            [
                'starts_at' => now(),
                'status' => 'active',
            ],
            $actor
        );
    }
}
