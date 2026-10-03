<?php

namespace App\Services\Subscription;

use App\Enums\SubscriptionStatus;
use App\Models\Building;
use App\Models\BuildingSubscription;
use App\Models\BuildingSubscriptionEvent;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SubscriptionLifecycleService
{
    public function create(
        Building $building,
        Plan $plan,
        array $attributes,
        ?User $actor
    ): BuildingSubscription {
        return DB::transaction(function () use (
            $building,
            $plan,
            $attributes,
            $actor
        ): BuildingSubscription {
            $startsAt = $attributes['starts_at'] ?? now();
            $expiresAt = $attributes['expires_at']
                ?? (
                    $plan->duration_days
                        ? now()->parse($startsAt)->addDays($plan->duration_days)
                        : null
                );

            $graceEndsAt = $attributes['grace_ends_at']
                ?? (
                    $expiresAt
                        ? now()->parse($expiresAt)->addDays(
                            max(0, (int) config('subscriptions.grace_days', 7))
                        )
                        : null
                );

            $subscription = BuildingSubscription::query()->create([
                'building_id' => $building->getKey(),
                'plan_id' => $plan->getKey(),
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'grace_ends_at' => $graceEndsAt,
                'status' => $attributes['status']
                    ?? SubscriptionStatus::Active->value,
                'limits' => $attributes['limits'] ?? null,
                'created_by' => $actor?->getKey(),
                'updated_by' => $actor?->getKey(),
            ]);

            $this->record(
                $subscription,
                'created',
                null,
                $subscription->status->value,
                $actor,
                ['plan_id' => $plan->getKey()]
            );

            return $subscription->load('plan');
        });
    }

    public function renew(
        BuildingSubscription $subscription,
        Plan $plan,
        array $attributes,
        ?User $actor
    ): BuildingSubscription {
        return DB::transaction(function () use (
            $subscription,
            $plan,
            $attributes,
            $actor
        ): BuildingSubscription {
            $previousStatus = $subscription->status->value;
            $startsAt = $attributes['starts_at']
                ?? (
                    $subscription->expires_at && $subscription->expires_at->isFuture()
                        ? $subscription->expires_at
                        : now()
                );

            $expiresAt = $attributes['expires_at']
                ?? (
                    $plan->duration_days
                        ? now()->parse($startsAt)->addDays($plan->duration_days)
                        : null
                );

            $graceEndsAt = $attributes['grace_ends_at']
                ?? (
                    $expiresAt
                        ? now()->parse($expiresAt)->addDays(
                            max(0, (int) config('subscriptions.grace_days', 7))
                        )
                        : null
                );

            $subscription->forceFill([
                'plan_id' => $plan->getKey(),
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'grace_ends_at' => $graceEndsAt,
                'status' => SubscriptionStatus::Active->value,
                'cancelled_at' => null,
                'limits' => $attributes['limits']
                    ?? $subscription->limits,
                'updated_by' => $actor?->getKey(),
            ])->save();

            $this->record(
                $subscription,
                'renewed',
                $previousStatus,
                SubscriptionStatus::Active->value,
                $actor,
                ['plan_id' => $plan->getKey()]
            );

            return $subscription->refresh()->load('plan');
        });
    }

    public function transition(
        BuildingSubscription $subscription,
        SubscriptionStatus $status,
        ?User $actor,
        string $eventType
    ): BuildingSubscription {
        return DB::transaction(function () use (
            $subscription,
            $status,
            $actor,
            $eventType
        ): BuildingSubscription {
            $from = $subscription->status->value;

            $subscription->forceFill([
                'status' => $status->value,
                'cancelled_at' => $status === SubscriptionStatus::Cancelled
                    ? now()
                    : null,
                'updated_by' => $actor?->getKey(),
            ])->save();

            $this->record(
                $subscription,
                $eventType,
                $from,
                $status->value,
                $actor
            );

            return $subscription->refresh()->load('plan');
        });
    }

    private function record(
        BuildingSubscription $subscription,
        string $eventType,
        ?string $fromStatus,
        ?string $toStatus,
        ?User $actor,
        array $metadata = []
    ): void {
        BuildingSubscriptionEvent::query()->create([
            'building_subscription_id' => $subscription->getKey(),
            'building_id' => $subscription->building_id,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'metadata' => $metadata ?: null,
            'actor_user_id' => $actor?->getKey(),
        ]);
    }
}
