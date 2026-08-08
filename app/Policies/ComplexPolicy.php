<?php

namespace App\Policies;

class ComplexPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'complexes';
    }
}
