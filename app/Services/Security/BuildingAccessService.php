<?php

namespace App\Services\Security;

use App\Models\Building;
use App\Models\UnitOccupancy;
use App\Models\UnitOwnership;
use App\Models\User;

class BuildingAccessService
{
    public function allows(User $user, Building $building): bool
    {
        if (! $user->is_active || $user->is_blocked) {
            return false;
        }

        if ($this->hasGlobalOrScopedRole($user, $building)) {
            return true;
        }

        return $this->hasResidentialAccess($user, $building);
    }

    private function hasGlobalOrScopedRole(User $user, Building $building): bool
    {
        return $user->userRoleAssignments()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->where(function ($query) use ($building): void {
                $query
                    ->where(function ($global): void {
                        $global->whereNull('scope_type')
                            ->whereNull('scope_id');
                    })
                    ->orWhere(function ($scoped) use ($building): void {
                        $scoped->where('scope_type', $building->getMorphClass())
                            ->where('scope_id', $building->getKey());
                    })
                    ->orWhere(function ($complexScope) use ($building): void {
                        $complexScope->where('scope_type', $building->complex->getMorphClass())
                            ->where('scope_id', $building->complex_id);
                    });
            })
            ->exists();
    }

    private function hasResidentialAccess(User $user, Building $building): bool
    {
        $unitIds = $building->blocks()
            ->with('floors.units:id,floor_id')
            ->get()
            ->flatMap(fn ($block) => $block->floors)
            ->flatMap(fn ($floor) => $floor->units)
            ->pluck('id');

        if ($unitIds->isEmpty()) {
            return false;
        }

        $occupancy = UnitOccupancy::query()
            ->where('user_id', $user->id)
            ->whereIn('unit_id', $unitIds)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now()->toDateString());
            })
            ->exists();

        if ($occupancy) {
            return true;
        }

        return UnitOwnership::query()
            ->where('user_id', $user->id)
            ->whereIn('unit_id', $unitIds)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now()->toDateString());
            })
            ->exists();
    }
}
