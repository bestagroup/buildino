<?php

namespace App\Policies;

use App\Models\Building;
use App\Models\User;

class BuildingExpensePolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'expenses';
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
