<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class BuildingResourceScopeService
{
    public function __construct(
        private readonly BuildingPermissionScopeService $permissions
    ) {
    }

    public function apply(
        Builder $query,
        User $user,
        string $permission,
        string $buildingColumn = 'building_id'
    ): Builder {
        $buildingIds = $this->permissions->buildingIds(
            $user,
            $permission
        );

        if ($buildingIds === null) {
            return $query;
        }

        if ($buildingIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn(
            $query->getModel()->qualifyColumn($buildingColumn),
            $buildingIds
        );
    }
}
