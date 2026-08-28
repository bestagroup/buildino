<?php

namespace App\Policies;

use App\Models\Building;
use App\Models\BuildingExpense;
use App\Models\Block;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

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
        ?Building $building = null,
        ?Block $block = null
    ): bool {
        if (! $building) {
            return false;
        }

        if (
            $block
            && (int) $block->building_id !== (int) $building->getKey()
        ) {
            return false;
        }

        return $this->permissions->allows(
            $user,
            $this->permission('create'),
            $building
        ) || (
            $block
            && $this->permissions->allows(
                $user,
                $this->permission('create'),
                $block
            )
        );
    }

    public function view(User $user, Model $expense): bool
    {
        return $this->allowsExpense($user, 'view', $expense);
    }

    public function update(User $user, Model $expense): bool
    {
        return $this->allowsExpense($user, 'update', $expense);
    }

    public function delete(User $user, Model $expense): bool
    {
        return $this->allowsExpense($user, 'delete', $expense);
    }

    private function allowsExpense(
        User $user,
        string $action,
        Model $expense
    ): bool {
        if (! $expense instanceof BuildingExpense) {
            return false;
        }

        $expense->loadMissing(['building', 'block']);

        return (
            $expense->building
            && $this->permissions->allows(
                $user,
                $this->permission($action),
                $expense->building
            )
        ) || (
            $expense->block
            && $this->permissions->allows(
                $user,
                $this->permission($action),
                $expense->block
            )
        );
    }
}
