<?php

namespace App\Policies;

class FinancialReconciliationPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'financial-reconciliations';
    }
}
