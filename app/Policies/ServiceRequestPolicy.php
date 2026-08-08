<?php

namespace App\Policies;

class ServiceRequestPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'service-requests';
    }
}
