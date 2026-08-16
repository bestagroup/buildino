<?php

namespace App\Policies;

use App\Models\Building;
use App\Models\User;

class MeetingMinutePolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'meeting-minutes';
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
