<?php

namespace App\Policies;

class SystemSettingPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'system-settings';
    }
}
