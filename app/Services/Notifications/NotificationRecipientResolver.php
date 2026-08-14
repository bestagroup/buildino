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
        $today = now()->toDateString();

        $occupants = UnitOccupancy::query()
            ->where('unit_id', $unitId)
            ->where('is_active', true)
            ->whereDate('starts_at', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', $today);
            })
            ->pluck('user_id');

        $owners = UnitOwnership::query()
            ->where('unit_id', $unitId)
            ->where('is_active', true)
            ->whereDate('starts_at', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', $today);
            })
            ->pluck('user_id');

        $userIds = $occupants
            ->merge($owners)
            ->unique()
            ->values();

        return User::query()
            ->whereIn('id', $userIds)
            ->where('is_active', true)
            ->where('is_blocked', false)
            ->get();
    }
}
