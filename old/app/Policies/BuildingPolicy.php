<?php

namespace App\Policies;

class BuildingPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'buildings';
    }
}
