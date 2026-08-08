<?php

namespace App\Policies;

class ReportDefinitionPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'reports';
    }
}
