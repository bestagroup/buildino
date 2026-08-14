<?php

namespace App\Policies;

class BlockPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'blocks';
    }
}
