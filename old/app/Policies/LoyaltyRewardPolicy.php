<?php

namespace App\Policies;

class LoyaltyRewardPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'loyalty-rewards';
    }
}
