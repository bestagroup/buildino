<?php

namespace App\Policies;

class BuildingSubscriptionPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'building-subscriptions';
    }
}
