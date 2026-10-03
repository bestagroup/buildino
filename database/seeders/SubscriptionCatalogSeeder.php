<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class SubscriptionCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $features = collect([
            ['code' => 'units.limit', 'title' => 'حداکثر واحد', 'value_type' => 'integer'],
            ['code' => 'reports.enabled', 'title' => 'گزارش‌های مدیریتی', 'value_type' => 'boolean'],
            ['code' => 'wallet.enabled', 'title' => 'کیف پول', 'value_type' => 'boolean'],
            ['code' => 'facilities.enabled', 'title' => 'رزرو امکانات', 'value_type' => 'boolean'],
            ['code' => 'services.enabled', 'title' => 'خدمات ساختمان', 'value_type' => 'boolean'],
            ['code' => 'loyalty.enabled', 'title' => 'باشگاه وفاداری', 'value_type' => 'boolean'],
        ])->mapWithKeys(function (array $definition): array {
            $feature = Feature::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'title' => $definition['title'],
                    'value_type' => $definition['value_type'],
                ]
            );

            return [$definition['code'] => $feature];
        });

        $plans = [
            'trial' => [
                'title' => 'آزمایشی',
                'price' => 0,
                'duration_days' => 14,
                'features' => [
                    'units.limit' => 20,
                    'reports.enabled' => true,
                    'wallet.enabled' => true,
                    'facilities.enabled' => true,
                    'services.enabled' => true,
                    'loyalty.enabled' => false,
                ],
            ],
            'basic' => [
                'title' => 'پایه',
                'price' => 0,
                'duration_days' => 30,
                'features' => [
                    'units.limit' => 50,
                    'reports.enabled' => true,
                    'wallet.enabled' => true,
                    'facilities.enabled' => true,
                    'services.enabled' => true,
                    'loyalty.enabled' => false,
                ],
            ],
            'pro' => [
                'title' => 'حرفه‌ای',
                'price' => 0,
                'duration_days' => 30,
                'features' => [
                    'units.limit' => 300,
                    'reports.enabled' => true,
                    'wallet.enabled' => true,
                    'facilities.enabled' => true,
                    'services.enabled' => true,
                    'loyalty.enabled' => true,
                ],
            ],
            'enterprise' => [
                'title' => 'سازمانی',
                'price' => 0,
                'duration_days' => 30,
                'features' => [
                    'units.limit' => null,
                    'reports.enabled' => true,
                    'wallet.enabled' => true,
                    'facilities.enabled' => true,
                    'services.enabled' => true,
                    'loyalty.enabled' => true,
                ],
            ],
        ];

        foreach ($plans as $code => $definition) {
            $plan = Plan::query()->updateOrCreate(
                ['code' => $code],
                [
                    'title' => $definition['title'],
                    'description' => 'Buildino subscription plan',
                    'price' => $definition['price'],
                    'duration_days' => $definition['duration_days'],
                    'is_active' => true,
                ]
            );

            $sync = [];

            foreach ($definition['features'] as $featureCode => $value) {
                $sync[$features[$featureCode]->getKey()] = [
                    'value' => json_encode($value, JSON_THROW_ON_ERROR),
                ];
            }

            $plan->features()->sync($sync);
        }
    }
}
