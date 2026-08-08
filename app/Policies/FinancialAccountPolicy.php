<?php

namespace App\Policies;

class FinancialAccountPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'financial-accounts';
    }
}
