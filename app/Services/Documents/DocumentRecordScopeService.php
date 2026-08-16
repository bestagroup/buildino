<?php

namespace App\Services\Documents;

use App\Models\Building;
use App\Models\Unit;
use App\Models\User;
use App\Services\Security\BuildingPermissionScopeService;
use Illuminate\Database\Eloquent\Builder;

final class DocumentRecordScopeService
{
    public function __construct(
        private readonly BuildingPermissionScopeService $permissions
    ) {
    }

    public function apply(
        Builder $query,
        User $user,
        string $permission
    ): Builder {
        $buildingIds = $this->permissions->buildingIds(
            $user,
            $permission
        );

        if ($buildingIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHasMorph(
            'documentable',
            [
                Building::class,
                Unit::class,
            ],
            function (
                Builder $targetQuery,
                string $targetType
            ) use ($buildingIds): void {
                if ($buildingIds === null) {
                    return;
                }

                if ($targetType === Building::class) {
                    $targetQuery->whereKey($buildingIds);

                    return;
                }

                $targetQuery->whereHas(
                    'floor.block',
                    fn (Builder $blockQuery): Builder =>
                        $blockQuery->whereIn(
                            'building_id',
                            $buildingIds
                        )
                );
            }
        );
    }
}
