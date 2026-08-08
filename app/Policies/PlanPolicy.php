<?php

namespace App\Policies;

class PlanPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'plans';
    }
}
