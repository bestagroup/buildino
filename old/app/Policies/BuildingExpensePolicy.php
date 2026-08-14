<?php

namespace App\Policies;

class BuildingExpensePolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'expenses';
    }
}
