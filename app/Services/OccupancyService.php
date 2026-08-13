<?php

namespace App\Services;

use App\Models\UnitOccupancy;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OccupancyService
{
    public function assign(
        array $data,
        ?User $actor = null
    ): UnitOccupancy {
        return DB::transaction(function () use (
            $data,
            $actor
        ): UnitOccupancy {
            $duplicateExists = UnitOccupancy::query()
                ->where('unit_id', $data['unit_id'])
                ->where('user_id', $data['user_id'])
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query
                        ->whereNull('ends_at')
                        ->orWhere('ends_at', '>=', now()->toDateString());
                })
                ->exists();

            if ($duplicateExists) {
                throw ValidationException::withMessages([
                    'user_id' => [
                        'This user already has an active occupancy for this unit.',
                    ],
                ]);
            }

            if (($data['is_primary'] ?? false) === true) {
                UnitOccupancy::query()
                    ->where('unit_id', $data['unit_id'])
                    ->where('is_primary', true)
                    ->where('is_active', true)
                    ->update([
                        'is_primary' => false,
                    ]);
            }

            $data['created_by'] ??= $actor?->getKey();

            return UnitOccupancy::query()->create($data);
        });
    }

    public function update(
        UnitOccupancy $occupancy,
        array $data
    ): UnitOccupancy {
        return DB::transaction(function () use (
            $occupancy,
            $data
        ): UnitOccupancy {
            if (($data['is_primary'] ?? false) === true) {
                UnitOccupancy::query()
                    ->where('unit_id', $occupancy->unit_id)
                    ->whereKeyNot($occupancy->getKey())
                    ->where('is_primary', true)
                    ->where('is_active', true)
                    ->update([
                        'is_primary' => false,
                    ]);
            }

            $occupancy->update($data);

            return $occupancy->refresh();
        });
    }

    public function end(
        UnitOccupancy $occupancy,
        User $actor,
        ?string $endsAt = null
    ): UnitOccupancy {
        return DB::transaction(function () use (
            $occupancy,
            $actor,
            $endsAt
        ): UnitOccupancy {
            if (! $occupancy->is_active) {
                return $occupancy->refresh();
            }

            $endDate = $endsAt ?? now()->toDateString();

            if (
                $occupancy->starts_at
                && $endDate < $occupancy->starts_at->toDateString()
            ) {
                throw ValidationException::withMessages([
                    'ends_at' => [
                        'The end date cannot be before the occupancy start date.',
                    ],
                ]);
            }

            $occupancy->update([
                'ends_at' => $endDate,
                'is_active' => false,
                'is_primary' => false,
                'ended_by' => $actor->getKey(),
            ]);

            return $occupancy->refresh();
        });
    }
}
