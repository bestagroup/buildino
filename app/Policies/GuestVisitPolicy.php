<?php

namespace App\Policies;

class GuestVisitPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'guest-visits';
    }
}
