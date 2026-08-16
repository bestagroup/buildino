<?php

namespace App\Policies;

use App\Models\Building;
use App\Models\User;

class BuildingIncomePolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'incomes';
    }

    public function viewAny(User $user): bool
    {
        return $this->permissions->allowsAnyScope(
            $user,
            $this->permission('view')
        );
    }

    public function create(
        User $user,
        ?Building $building = null
    ): bool {
        return $building !== null
            && $this->permissions->allows(
                $user,
                $this->permission('create'),
                $building
            );
    }
}
