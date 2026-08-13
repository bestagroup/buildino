<?php

namespace App\Policies;

use App\Models\Building;
use App\Models\Floor;
use App\Models\Unit;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Database\Eloquent\Model;

abstract class BasePolicy
{
    public function __construct(
        protected readonly PermissionChecker $permissions
    ) {
    }

    abstract protected function permissionPrefix(): string;

    public function viewAny(User $user): bool
    {
        return $this->permissions->allows(
            $user,
            $this->permission('view')
        );
    }

    public function view(User $user, Model $model): bool
    {
        return $this->permissions->allows(
            $user,
            $this->permission('view'),
            $this->resolveScope($model)
        );
    }

    public function create(User $user): bool
    {
        return $this->permissions->allows(
            $user,
            $this->permission('create')
        );
    }

    public function update(User $user, Model $model): bool
    {
        return $this->permissions->allows(
            $user,
            $this->permission('update'),
            $this->resolveScope($model)
        );
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->permissions->allows(
            $user,
            $this->permission('delete'),
            $this->resolveScope($model)
        );
    }

    protected function permission(string $action): string
    {
        return sprintf(
            '%s.%s',
            $this->permissionPrefix(),
            $action
        );
    }

    protected function resolveScope(Model $model): ?Model
    {
        if ($model instanceof Building) {
            return $model;
        }

        $buildingId = $model->getAttribute('building_id');

        if ($buildingId !== null) {
            return Building::query()->find($buildingId);
        }

        if ($model instanceof Floor) {
            $model->loadMissing('block.building');

            return $model->block?->building;
        }

        if ($model instanceof Unit) {
            $model->loadMissing('floor.block.building');

            return $model->floor?->block?->building;
        }

        /*
         * Unit-scoped resources such as:
         * UnitOwnership, UnitOccupancy, UnitInvitation, GuestVisit, etc.
         */
        if (
            method_exists($model, 'unit')
            && $model->getAttribute('unit_id') !== null
        ) {
            $model->loadMissing('unit.floor.block.building');

            return $model->unit?->floor?->block?->building;
        }

        return null;
    }
}
