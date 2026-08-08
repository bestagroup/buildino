<?php

namespace App\Policies;

class FacilityReservationPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'facility-reservations';
    }
    public function approve(\App\Models\User $user, \App\Models\FacilityReservation $reservation): bool
    {
        return $this->permissions->allows($user, 'facility-reservations.approve', $this->scope($reservation));
    }

    public function cancel(\App\Models\User $user, \App\Models\FacilityReservation $reservation): bool
    {
        return $this->permissions->allows($user, 'facility-reservations.cancel', $this->scope($reservation));
    }
}
