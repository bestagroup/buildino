<?php

namespace App\Services\Notifications;

use App\Models\UnitOccupancy;
use App\Models\UnitOwnership;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationRecipientResolver
{
    public function forUnit(int $unitId): Collection
    {
        $userIds = UnitOccupancy::query()
            ->where('unit_id', $unitId)
            ->where('is_active', true)
            ->pluck('user_id')
            ->merge(
                UnitOwnership::query()
                    ->where('unit_id', $unitId)
                    ->where('is_active', true)
                    ->pluck('user_id')
            )
            ->unique()
            ->values();

        return User::query()
            ->whereIn('id', $userIds)
            ->where('is_active', true)
            ->where('is_blocked', false)
            ->get();
    }
}
