<?php

namespace App\Policies;

class UserPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'users';
    }
}
