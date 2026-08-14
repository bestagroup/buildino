<?php

namespace App\Policies;

class AccountingPeriodPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'accounting-periods';
    }
}
