<?php

namespace Tests\Feature\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Models\Building;
use App\Models\BuildingSubscription;
use App\Models\Complex;
use App\Models\Plan;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_can_be_activated_and_renewed_with_grace_period(): void
    {
        $user = User::factory()->create();
        $complex = Complex::query()->create([
            'code' => 'C-1',
            'title' => 'Complex',
            'province' => 'Tehran',
            'city' => 'Tehran',
            'is_active' => true,
        ]);

        $building = Building::query()->create([
            'complex_id' => $complex->getKey(),
            'code' => 'B-1',
            'title' => 'Building',
            'timezone' => 'Asia/Tehran',
            'currency' => 'IRR',
            'is_active' => true,
        ]);

        $plan = Plan::query()->create([
            'code' => 'pro',
            'title' => 'Pro',
            'price' => 0,
            'duration_days' => 30,
            'is_active' => true,
        ]);

        $service = app(SubscriptionService::class);

        $subscription = $service->activate(
            $building,
            $plan,
            $user,
            now()->subDay(),
            30,
            7
        );

        $this->assertTrue($subscription->isUsable());
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertNotNull($subscription->expires_at);
        $this->assertNotNull($subscription->grace_ends_at);

        $oldExpiry = $subscription->expires_at->copy();

        $subscription = $service->renew($subscription, $user, 30, 7);

        $this->assertTrue($subscription->expires_at->greaterThan($oldExpiry));
        $this->assertNotNull($subscription->renewed_at);
        $this->assertTrue($subscription->isUsable());
    }


    public function test_ensure_trial_never_reissues_trial_after_subscription_history_exists(): void
    {
        $complex = Complex::query()->create([
            'code' => 'C-TRIAL-LOCK',
            'title' => 'Trial Lock Complex',
            'province' => 'Tehran',
            'city' => 'Tehran',
            'is_active' => true,
        ]);

        $building = Building::query()->create([
            'complex_id' => $complex->getKey(),
            'code' => 'B-TRIAL-LOCK',
            'title' => 'Trial Lock Building',
            'timezone' => 'Asia/Tehran',
            'currency' => 'IRR',
            'is_active' => true,
        ]);

        $service = app(SubscriptionService::class);

        $initial = $service->current($building);
        $this->assertNotNull($initial);

        $initial->forceFill([
            'status' => SubscriptionStatus::Expired,
            'expires_at' => now()->subDay(),
            'grace_ends_at' => now()->subHour(),
        ])->save();

        $again = $service->ensureTrial(
            $building->fresh()
        );

        $this->assertSame(
            $initial->id,
            $again->id
        );

        $this->assertSame(
            SubscriptionStatus::Expired,
            $again->status
        );

        $this->assertSame(
            1,
            BuildingSubscription::query()
                ->where('building_id', $building->id)
                ->count()
        );
    }

    public function test_suspended_subscription_is_not_usable(): void
    {
        $user = User::factory()->create();
        $complex = Complex::query()->create([
            'code' => 'C-2',
            'title' => 'Complex 2',
            'province' => 'Tehran',
            'city' => 'Tehran',
            'is_active' => true,
        ]);

        $building = Building::query()->create([
            'complex_id' => $complex->getKey(),
            'code' => 'B-2',
            'title' => 'Building 2',
            'timezone' => 'Asia/Tehran',
            'currency' => 'IRR',
            'is_active' => true,
        ]);

        $plan = Plan::query()->create([
            'code' => 'basic',
            'title' => 'Basic',
            'price' => 0,
            'duration_days' => 30,
            'is_active' => true,
        ]);

        $service = app(SubscriptionService::class);
        $subscription = $service->activate($building, $plan, $user);

        $subscription = $service->suspend($subscription, $user, 'test');

        $this->assertSame(SubscriptionStatus::Suspended, $subscription->status);
        $this->assertFalse($subscription->isUsable());
        $this->assertNotNull($subscription->suspended_at);
    }
}
