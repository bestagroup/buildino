<?php

namespace App\Policies;

class MeetingMinutePolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'meeting-minutes';
    }
}
