<?php

namespace App\Services\Security;

use App\Models\Building;
use App\Models\Complex;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class BuildingPermissionScopeService
{
    /**
     * Return null for global access, otherwise the explicit Building ids that
     * are covered by active Building/Complex assignments for the permission.
     *
     * @return array<int>|null
     */
    public function buildingIds(
        User $user,
        string $permission
    ): ?array {
        if (! $user->is_active || $user->is_blocked) {
            return [];
        }

        $assignments = $user->userRoleAssignments()
            ->with('role.permissions:id,name')
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
            ->whereHas(
                'role.permissions',
                fn (Builder $query) => $query->where(
                    'permissions.name',
                    $permission
                )
            )
            ->get();

        if ($assignments->contains(
            fn ($assignment): bool =>
                $assignment->scope_type === null
                && $assignment->scope_id === null
        )) {
            return null;
        }

        $buildingMorph = (new Building())->getMorphClass();
        $complexMorph = (new Complex())->getMorphClass();

        $buildingIds = $assignments
            ->where('scope_type', $buildingMorph)
            ->pluck('scope_id')
            ->filter()
            ->map(fn ($id): int => (int) $id);

        $complexIds = $assignments
            ->where('scope_type', $complexMorph)
            ->pluck('scope_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values();

        if ($complexIds->isNotEmpty()) {
            $buildingIds = $buildingIds->merge(
                Building::query()
                    ->whereIn('complex_id', $complexIds)
                    ->pluck('id')
            );
        }

        return $buildingIds
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
