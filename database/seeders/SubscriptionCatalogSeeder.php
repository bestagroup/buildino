<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Seeder;

class SubscriptionCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $featureModels = [];

        foreach (config('subscriptions.features', []) as $code => $definition) {
            $featureModels[$code] = Feature::query()->updateOrCreate(
                ['code' => $code],
                [
                    'title' => $definition['title'] ?? $code,
                    'description' => $definition['description'] ?? null,
                    'value_type' => $definition['value_type'] ?? 'boolean',
                ]
            );
        }

        $plans = [
            'trial' => [
                'title' => 'آزمایشی',
                'description' => 'پلن آزمایشی اولیه ساختمان',
                'duration_days' => max(
                    1,
                    (int) config('subscriptions.default_trial_days', 14)
                ),
            ],
            'basic' => [
                'title' => 'پایه',
                'description' => 'پلن پایه مدیریت ساختمان',
                'duration_days' => null,
            ],
            'pro' => [
                'title' => 'حرفه‌ای',
                'description' => 'پلن حرفه‌ای مدیریت ساختمان',
                'duration_days' => null,
            ],
            'enterprise' => [
                'title' => 'سازمانی',
                'description' => 'پلن سازمانی برای ساختمان‌ها و مجتمع‌های بزرگ',
                'duration_days' => null,
            ],
        ];

        foreach ($plans as $code => $definition) {
            $plan = Plan::query()->updateOrCreate(
                ['code' => $code],
                [
                    'title' => $definition['title'],
                    'description' => $definition['description'],
                    'price' => 0,
                    'duration_days' => $definition['duration_days'],
                    'is_active' => true,
                ]
            );

            /*
             * Seed a functional catalog without inventing commercial
             * restrictions. Pricing and limits are intentionally left for the
             * platform administrator. Boolean capabilities default to enabled.
             */
            foreach ($featureModels as $featureCode => $feature) {
                $value = ($feature->value_type?->value ?? $feature->value_type)
                    === 'boolean'
                        ? true
                        : null;

                PlanFeature::query()->updateOrCreate(
                    [
                        'plan_id' => $plan->getKey(),
                        'feature_id' => $feature->getKey(),
                    ],
                    [
                        'value' => $value,
                    ]
                );
            }
        }
    }
}
