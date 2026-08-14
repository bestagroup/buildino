<?php

namespace App\Policies;

class StorageUnitPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'storage-units';
    }
}
