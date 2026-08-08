<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Database\Eloquent\Model;

abstract class BasePolicy
{
    public function __construct(
        protected PermissionChecker $permissions,
    ) {}

    abstract protected function permissionPrefix(): string;

    protected function scope(Model $model): ?Model
    {
        if (method_exists($model, 'building') && $model->getAttribute('building_id')) {
            return $model->building;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $this->permissions->allows($user, $this->permissionPrefix().'.view');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->permissions->allows(
            $user,
            $this->permissionPrefix().'.view',
            $this->scope($model),
        );
    }

    public function create(User $user): bool
    {
        return $this->permissions->allows($user, $this->permissionPrefix().'.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->permissions->allows(
            $user,
            $this->permissionPrefix().'.update',
            $this->scope($model),
        );
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->permissions->allows(
            $user,
            $this->permissionPrefix().'.delete',
            $this->scope($model),
        );
    }

    public function restore(User $user, Model $model): bool
    {
        return $this->permissions->allows(
            $user,
            $this->permissionPrefix().'.restore',
            $this->scope($model),
        );
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return $this->permissions->allows(
            $user,
            $this->permissionPrefix().'.force-delete',
            $this->scope($model),
        );
    }
}
