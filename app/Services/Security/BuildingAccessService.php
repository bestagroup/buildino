<?php

namespace App\Services\Security;

use App\Models\Building;
use App\Models\UnitOccupancy;
use App\Models\UnitOwnership;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class BuildingAccessService
{
    public function allows(
        User $user,
        Building $building
    ): bool {
        if (! $user->is_active || $user->is_blocked) {
            return false;
        }

        if ($this->hasGlobalOrScopedRole($user, $building)) {
            return true;
        }

        return $this->hasResidentialAccess(
            $user,
            $building
        );
    }

    private function hasGlobalOrScopedRole(
        User $user,
        Building $building
    ): bool {
        $building->loadMissing('complex');

        return $user->userRoleAssignments()
            ->where('is_active', true)

            ->where(function (Builder $query): void {
                $query
                    ->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })

            ->where(function (Builder $query): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })

            ->where(function (Builder $query) use ($building): void {

                // Global
                $query->where(function (Builder $global): void {
                    $global
                        ->whereNull('scope_type')
                        ->whereNull('scope_id');
                });

                // Building
                $query->orWhere(
                    function (Builder $scoped) use ($building): void {
                        $scoped
                            ->where(
                                'scope_type',
                                $building->getMorphClass()
                            )
                            ->where(
                                'scope_id',
                                $building->getKey()
                            );
                    }
                );

                // Complex
                if ($building->complex !== null) {
                    $query->orWhere(
                        function (Builder $complexScope) use ($building): void {
                            $complexScope
                                ->where(
                                    'scope_type',
                                    $building->complex->getMorphClass()
                                )
                                ->where(
                                    'scope_id',
                                    $building->complex_id
                                );
                        }
                    );
                }
            })

            ->exists();
    }

    private function hasResidentialAccess(
        User $user,
        Building $building
    ): bool {
        if ($this->hasActiveOccupancy($user, $building)) {
            return true;
        }

        return $this->hasActiveOwnership(
            $user,
            $building
        );
    }

    private function hasActiveOccupancy(
        User $user,
        Building $building
    ): bool {
        return UnitOccupancy::query()

            ->where('user_id', $user->getKey())
            ->where('is_active', true)

            ->where(function (Builder $query): void {
                $query
                    ->whereNull('starts_at')
                    ->orWhereDate('starts_at', '<=', today());
            })

            ->where(function (Builder $query): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', today());
            })

            ->whereHas(
                'unit.floor.block',
                fn (Builder $query) => $query->where(
                    'building_id',
                    $building->getKey()
                )
            )

            ->exists();
    }

    private function hasActiveOwnership(
        User $user,
        Building $building
    ): bool {
        return UnitOwnership::query()

            ->where('user_id', $user->getKey())
            ->where('is_active', true)

            ->where(function (Builder $query): void {
                $query
                    ->whereNull('starts_at')
                    ->orWhereDate('starts_at', '<=', today());
            })

            ->where(function (Builder $query): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', today());
            })

            ->whereHas(
                'unit.floor.block',
                fn (Builder $query) => $query->where(
                    'building_id',
                    $building->getKey()
                )
            )

            ->exists();
    }
}
