<?php

namespace App\Policies;

class FilePolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'files';
    }
}
