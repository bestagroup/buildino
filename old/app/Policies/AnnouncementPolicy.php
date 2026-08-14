<?php

namespace App\Policies;

class AnnouncementPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'announcements';
    }
}
