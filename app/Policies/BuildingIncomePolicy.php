<?php

namespace App\Policies;

class BuildingIncomePolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'incomes';
    }
}
