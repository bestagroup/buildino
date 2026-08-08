<?php

namespace App\Services;

use App\Models\UnitOccupancy;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OccupancyService
{
    public function assign(array $data, ?User $actor = null): UnitOccupancy
    {
        return DB::transaction(function () use ($data, $actor): UnitOccupancy {
            if (($data['is_primary'] ?? false) === true) {
                UnitOccupancy::query()->where('unit_id', $data['unit_id'])->where('is_primary', true)->where('is_active', true)
                    ->update(['is_primary' => false]);
            }
            $data['created_by'] ??= $actor?->getKey();
            return UnitOccupancy::query()->create($data);
        });
    }

    public function end(UnitOccupancy $occupancy, User $actor, ?string $endsAt = null): UnitOccupancy
    {
        return DB::transaction(function () use ($occupancy, $actor, $endsAt): UnitOccupancy {
            $occupancy->update(['ends_at' => $endsAt ?? now()->toDateString(), 'is_active' => false, 'ended_by' => $actor->getKey()]);
            return $occupancy->refresh();
        });
    }
}
