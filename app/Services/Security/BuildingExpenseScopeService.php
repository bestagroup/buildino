<?php

namespace App\Services\Security;

use App\Models\Block;
use App\Models\Building;
use App\Models\Complex;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class BuildingExpenseScopeService
{
    public function apply(
        Builder $query,
        User $user,
        string $permission = 'expenses.view'
    ): Builder {
        $assignments = $user->userRoleAssignments()
            ->active()
            ->whereHas(
                'role.permissions',
                fn (Builder $permissions) => $permissions->where(
                    'permissions.name',
                    $permission
                )
            )
            ->get([
                'scope_type',
                'scope_id',
            ]);

        if ($assignments->contains(
            fn ($assignment): bool => $assignment->scope_type === null
                && $assignment->scope_id === null
        )) {
            return $query;
        }

        $buildingIds = $this->scopeIds($assignments, Building::class);
        $complexIds = $this->scopeIds($assignments, Complex::class);
        $blockIds = $this->scopeIds($assignments, Block::class);

        if ($complexIds !== []) {
            $buildingIds = array_values(array_unique([
                ...$buildingIds,
                ...Building::query()
                    ->whereIn('complex_id', $complexIds)
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all(),
            ]));
        }

        if ($buildingIds === [] && $blockIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scope) use ($buildingIds, $blockIds): void {
            if ($buildingIds !== []) {
                $scope->whereIn('building_id', $buildingIds);
            }

            if ($blockIds !== []) {
                $method = $buildingIds === [] ? 'whereIn' : 'orWhereIn';
                $scope->{$method}('block_id', $blockIds);
            }
        });
    }

    private function scopeIds($assignments, string $modelClass): array
    {
        $model = new $modelClass;

        return $assignments
            ->filter(
                fn ($assignment): bool => in_array(
                    $assignment->scope_type,
                    [$modelClass, $model->getMorphClass()],
                    true
                )
            )
            ->pluck('scope_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
