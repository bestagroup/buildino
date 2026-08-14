<?php

namespace App\Policies;

class UnitOccupancyPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'unit-occupancies';
    }
}
