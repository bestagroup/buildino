<?php

namespace App\Policies;

class ParkingSpacePolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'parking-spaces';
    }
}
