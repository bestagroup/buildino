<?php

namespace App\Policies;

class ChargePeriodPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'charge-periods';
    }
}
