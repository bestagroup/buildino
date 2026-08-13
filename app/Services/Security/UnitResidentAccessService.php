<?php

namespace App\Services\Security;

use App\Models\Unit;
use App\Models\UnitOccupancy;
use App\Models\UnitOwnership;
use App\Models\User;

final class UnitResidentAccessService
{
    public function allows(
        User $user,
        Unit $unit
    ): bool {
        if (
            ! $user->is_active
            || $user->is_blocked
        ) {
            return false;
        }

        $today = now()->toDateString();

        $occupancy = UnitOccupancy::query()
            ->where('unit_id', $unit->getKey())
            ->where('user_id', $user->getKey())
            ->where('is_active', true)
            ->whereDate('starts_at', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', $today);
            })
            ->exists();

        if ($occupancy) {
            return true;
        }

        return UnitOwnership::query()
            ->where('unit_id', $unit->getKey())
            ->where('user_id', $user->getKey())
            ->where('is_active', true)
            ->whereDate('starts_at', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', $today);
            })
            ->exists();
    }
}
