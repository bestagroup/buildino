<?php

namespace App\Services\Loyalty;

use App\Models\LoyaltyReward;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Collection;

final class LoyaltyAccessService
{
    /** @return Collection<int, int> */
    public function residentBuildingIds(User $user): Collection
    {
        return Unit::query()
            ->where(function ($query) use ($user): void {
                $query
                    ->whereHas('unitOwnerships', function ($ownership) use ($user): void {
                        $ownership
                            ->where('user_id', $user->getKey())
                            ->where('is_active', true)
                            ->whereDate('starts_at', '<=', today())
                            ->where(function ($dates): void {
                                $dates
                                    ->whereNull('ends_at')
                                    ->orWhereDate('ends_at', '>=', today());
                            });
                    })
                    ->orWhereHas('unitOccupancies', function ($occupancy) use ($user): void {
                        $occupancy
                            ->where('user_id', $user->getKey())
                            ->where('is_active', true)
                            ->whereDate('starts_at', '<=', today())
                            ->where(function ($dates): void {
                                $dates
                                    ->whereNull('ends_at')
                                    ->orWhereDate('ends_at', '>=', today());
                            });
                    });
            })
            ->with('floor.block:id,building_id')
            ->get()
            ->map(fn (Unit $unit): ?int => $unit->floor?->block?->building_id
                    ? (int) $unit->floor->block->building_id
                    : null
            )
            ->filter()
            ->unique()
            ->values();
    }

    public function canClaim(User $user, LoyaltyReward $reward): bool
    {
        return $reward->building_id === null
            || $this->residentBuildingIds($user)->contains(
                (int) $reward->building_id
            );
    }
}
