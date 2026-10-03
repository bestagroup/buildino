<?php

namespace App\Observers;

use App\Models\Building;
use App\Services\Subscriptions\SubscriptionService;

final class ProvisionSubscriptionObserver
{
    public function __construct(
        private readonly SubscriptionService $subscriptions
    ) {
    }

    public function created(Building $building): void
    {
        $this->subscriptions->ensureTrial($building);
    }
}
