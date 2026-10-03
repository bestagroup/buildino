<?php

namespace App\Services\Subscription;

use App\Models\Building;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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

        $plan = $this->trialPlan();

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

    private function trialPlan(): Plan
    {
        return DB::transaction(function (): Plan {
            $plan = Plan::query()->firstOrCreate(
                ['code' => 'trial'],
                [
                    'title' => 'آزمایشی',
                    'description' => 'پلن آزمایشی اولیه ساختمان',
                    'price' => 0,
                    'duration_days' => max(
                        1,
                        (int) config('subscriptions.default_trial_days', 14)
                    ),
                    'is_active' => true,
                ]
            );

            if (! $plan->is_active) {
                $plan->forceFill(['is_active' => true])->save();
            }

            foreach (config('subscriptions.features', []) as $code => $definition) {
                $feature = Feature::query()->firstOrCreate(
                    ['code' => $code],
                    [
                        'title' => $definition['title'] ?? $code,
                        'description' => $definition['description'] ?? null,
                        'value_type' => $definition['value_type'] ?? 'boolean',
                    ]
                );

                PlanFeature::query()->firstOrCreate(
                    [
                        'plan_id' => $plan->getKey(),
                        'feature_id' => $feature->getKey(),
                    ],
                    [
                        'value' => ($definition['value_type'] ?? 'boolean')
                            === 'boolean'
                                ? true
                                : null,
                    ]
                );
            }

            return $plan->refresh();
        });
    }
}
