<?php

namespace App\Services\Security;

use App\Models\Building;
use App\Models\BuildingFacility;
use App\Models\FacilityReservation;
use App\Models\Unit;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;

final class FacilityAccessService
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly BuildingAccessService $buildingAccess,
        private readonly UnitResidentAccessService $residentAccess
    ) {}

    public function canAccessBuilding(User $user, Building $building): bool
    {
        return $this->buildingAccess->allows($user, $building);
    }

    public function canViewFacility(User $user, BuildingFacility $facility): bool
    {
        $facility->loadMissing('building');

        if (
            $facility->building === null
            || ! $this->canAccessBuilding($user, $facility->building)
        ) {
            return false;
        }

        if ($facility->is_active) {
            return true;
        }

        return $this->permissions->allows(
            $user,
            'facilities.view',
            $facility->building
        );
    }

    public function canViewAllFacilities(User $user, Building $building): bool
    {
        return $this->permissions->allows($user, 'facilities.view', $building);
    }

    public function canCreateFacility(User $user, Building $building): bool
    {
        return $this->permissions->allows($user, 'facilities.create', $building);
    }

    public function canManageFacility(User $user, BuildingFacility $facility, string $action = 'update'): bool
    {
        $facility->loadMissing('building');

        return $facility->building !== null
            && $this->permissions->allows($user, "facilities.{$action}", $facility->building);
    }

    public function canReserveForUnit(User $user, BuildingFacility $facility, Unit $unit): bool
    {
        $facility->loadMissing('building');
        $unit->loadMissing('floor.block.building');

        $facilityBuilding = $facility->building;
        $unitBuilding = $unit->floor?->block?->building;

        if (! $facilityBuilding || ! $unitBuilding || (int) $facilityBuilding->getKey() !== (int) $unitBuilding->getKey()) {
            return false;
        }

        if ($this->permissions->allows($user, 'facility-reservations.create', $facilityBuilding)) {
            return true;
        }

        return $this->residentAccess->allows($user, $unit);
    }

    public function canViewReservation(User $user, FacilityReservation $reservation): bool
    {
        if ((int) $reservation->user_id === (int) $user->getKey()) {
            return true;
        }

        $building = $this->reservationBuilding($reservation);

        return $building !== null
            && $this->permissions->allows($user, 'facility-reservations.view', $building);
    }

    public function canApproveReservation(User $user, FacilityReservation $reservation): bool
    {
        $building = $this->reservationBuilding($reservation);

        return $building !== null
            && $this->permissions->allows($user, 'facility-reservations.approve', $building);
    }

    public function canCancelReservation(User $user, FacilityReservation $reservation): bool
    {
        if ((int) $reservation->user_id === (int) $user->getKey()) {
            return true;
        }

        $building = $this->reservationBuilding($reservation);

        return $building !== null
            && $this->permissions->allows($user, 'facility-reservations.cancel', $building);
    }

    public function canForceCancelReservation(User $user, FacilityReservation $reservation): bool
    {
        $building = $this->reservationBuilding($reservation);

        return $building !== null
            && $this->permissions->allows($user, 'facility-reservations.cancel', $building);
    }

    public function canViewAllReservationsGlobally(User $user): bool
    {
        return $this->permissions->allows($user, 'facility-reservations.view');
    }

    public function canViewAllReservationsInBuilding(User $user, Building $building): bool
    {
        return $this->permissions->allows($user, 'facility-reservations.view', $building);
    }

    private function reservationBuilding(FacilityReservation $reservation): ?Building
    {
        $reservation->loadMissing('buildingFacility.building');

        return $reservation->buildingFacility?->building;
    }
}
