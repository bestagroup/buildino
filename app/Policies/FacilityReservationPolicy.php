<?php

namespace App\Policies;

use App\Models\FacilityReservation;
use App\Models\User;

class FacilityReservationPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'facility-reservations';
    }

    public function approve(
        User $user,
        FacilityReservation $reservation
    ): bool {
        return $this->permissions->allows(
            $user,
            'facility-reservations.approve',
            $this->resolveScope($reservation)
        );
    }

    public function cancel(
        User $user,
        FacilityReservation $reservation
    ): bool {
        return $this->permissions->allows(
            $user,
            'facility-reservations.cancel',
            $this->resolveScope($reservation)
        );
    }
}
