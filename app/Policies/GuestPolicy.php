<?php

namespace App\Policies;

class GuestPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'guests';
    }
}
