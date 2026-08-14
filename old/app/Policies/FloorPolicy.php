<?php

namespace App\Policies;

class FloorPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'floors';
    }
}
