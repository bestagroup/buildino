<?php

namespace App\Policies;

class ChargeFormulaPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'charge-formulas';
    }
}
