<?php

namespace App\Policies;

class UnitPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'units';
    }
}
