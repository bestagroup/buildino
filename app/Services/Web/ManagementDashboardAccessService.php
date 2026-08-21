<?php

namespace App\Services\Web;

use App\Models\Building;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Support\Collection;

final class ManagementDashboardAccessService
{
    public function __construct(
        private readonly PermissionChecker $permissions
    ) {
    }

    public function hasPlatformAccess(
        User $user
    ): bool {
        return $this->permissions->allows(
            $user,
            'reports.platform.view',
            null
        );
    }

    public function hasAnyAccess(
        User $user
    ): bool {
        if ($this->hasPlatformAccess($user)) {
            return true;
        }

        return $this->accessibleBuildings(
            $user
        )->isNotEmpty()
            || $this->permissions->allowsAnyScope(
                $user,
                'reports.dashboard.view'
            );
    }

    public function allowsBuilding(
        User $user,
        Building $building
    ): bool {
        if ($this->hasPlatformAccess($user)) {
            return true;
        }

        return $this->permissions->allows(
            $user,
            'reports.dashboard.view',
            $building
        );
    }

    /**
     * @return Collection<int, Building>
     */
    public function accessibleBuildings(
        User $user
    ): Collection {
        $query = Building::query()
            ->with('complex:id,code,title')
            ->orderBy('title')
            ->orderBy('id');

        if ($this->hasPlatformAccess($user)) {
            return $query->get();
        }

        return $query
            ->get()
            ->filter(
                fn (Building $building): bool =>
                    $this->permissions->allows(
                        $user,
                        'reports.dashboard.view',
                        $building
                    )
            )
            ->values();
    }
}
