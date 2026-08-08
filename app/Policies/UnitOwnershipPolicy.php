<?php

namespace App\Policies;

class UnitOwnershipPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'unit-ownerships';
    }
}
