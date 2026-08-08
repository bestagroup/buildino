<?php

namespace App\Policies;

class BuildingFacilityPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'facilities';
    }
}
