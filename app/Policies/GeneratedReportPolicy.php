<?php

namespace App\Policies;

class GeneratedReportPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'generated-reports';
    }
}
