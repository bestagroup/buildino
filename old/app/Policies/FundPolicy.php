<?php

namespace App\Policies;

class FundPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'funds';
    }
}
