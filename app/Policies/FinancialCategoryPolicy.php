<?php

namespace App\Policies;

class FinancialCategoryPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'financial-categories';
    }
}
