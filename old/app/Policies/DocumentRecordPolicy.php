<?php

namespace App\Policies;

class DocumentRecordPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'documents';
    }
}
