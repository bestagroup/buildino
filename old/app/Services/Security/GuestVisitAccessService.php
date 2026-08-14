<?php

namespace App\Services\Security;

use App\Models\GuestVisit;
use App\Models\Unit;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;

final class GuestVisitAccessService
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly UnitResidentAccessService $residentAccess
    ) {
    }

    public function allowsForUnit(
        User $user,
        Unit $unit,
        string $action,
        bool $allowResident = true
    ): bool {
        $unit->loadMissing(
            'floor.block.building'
        );

        $building = $unit
            ->floor
            ?->block
            ?->building;

        if (! $building) {
            return false;
        }

        if (
            $this->permissions->allows(
                $user,
                "guest-visits.{$action}",
                $building
            )
        ) {
            return true;
        }

        if (! $allowResident) {
            return false;
        }

        return $this->residentAccess->allows(
            $user,
            $unit
        );
    }

    public function allowsForVisit(
        User $user,
        GuestVisit $visit,
        string $action,
        bool $allowResident = true
    ): bool {
        $visit->loadMissing(
            'unit.floor.block.building'
        );

        if (! $visit->unit) {
            return false;
        }

        return $this->allowsForUnit(
            $user,
            $visit->unit,
            $action,
            $allowResident
        );
    }
}
