<?php

namespace App\Support\Authorization;

use App\Models\Building;
use App\Models\Complex;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class BuildingVisibilityQuery
{
    public function apply(
        Builder $query,
        User $user
    ): Builder {
        if (! $user->is_active || $user->is_blocked) {
            return $query->whereRaw('1 = 0');
        }

        $assignments = $user->userRoleAssignments()
            ->where('is_active', true)
            ->whereHas(
                'role.permissions',
                fn (Builder $query) => $query->where(
                    'permissions.name',
                    'buildings.view'
                )
            )
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            });

        /*
        |--------------------------------------------------------------------------
        | Global Access
        |--------------------------------------------------------------------------
        |
        | A global assignment grants visibility to all buildings.
        |
        */

        $hasGlobalAccess = (clone $assignments)
            ->whereNull('scope_type')
            ->whereNull('scope_id')
            ->exists();

        if ($hasGlobalAccess) {
            return $query;
        }

        /*
        |--------------------------------------------------------------------------
        | Supported Scope Types
        |--------------------------------------------------------------------------
        */

        $complexScopeTypes = array_values(array_unique([
            (new Complex())->getMorphClass(),
            Complex::class,
        ]));

        $buildingScopeTypes = array_values(array_unique([
            (new Building())->getMorphClass(),
            Building::class,
        ]));

        /*
        |--------------------------------------------------------------------------
        | Complex Scoped Assignments
        |--------------------------------------------------------------------------
        */

        $complexIds = (clone $assignments)
            ->whereIn('scope_type', $complexScopeTypes)
            ->whereNotNull('scope_id')
            ->pluck('scope_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Building Scoped Assignments
        |--------------------------------------------------------------------------
        */

        $buildingIds = (clone $assignments)
            ->whereIn('scope_type', $buildingScopeTypes)
            ->whereNotNull('scope_id')
            ->pluck('scope_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        return $this->applyVisibilityConstraints(
            $query,
            $complexIds,
            $buildingIds
        );
    }

    private function applyVisibilityConstraints(
        Builder $query,
        Collection $complexIds,
        Collection $buildingIds
    ): Builder {
        if (
            $complexIds->isEmpty()
            && $buildingIds->isEmpty()
        ) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(
            function (Builder $query) use (
                $complexIds,
                $buildingIds
            ): void {
                if ($complexIds->isNotEmpty()) {
                    $query->whereIn(
                        'complex_id',
                        $complexIds->all()
                    );
                }

                if ($buildingIds->isNotEmpty()) {
                    if ($complexIds->isNotEmpty()) {
                        $query->orWhereIn(
                            'id',
                            $buildingIds->all()
                        );
                    } else {
                        $query->whereIn(
                            'id',
                            $buildingIds->all()
                        );
                    }
                }
            }
        );
    }
}
