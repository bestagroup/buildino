<?php

namespace App\Policies;

class UnitInvitationPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'unit-invitations';
    }
}
