<?php

namespace App\Services;

use App\Models\UnitOwnership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UnitOwnershipService
{
    public function assign(
        array $data,
        User $actor
    ): UnitOwnership {
        return DB::transaction(function () use ($data, $actor): UnitOwnership {
            $this->validateOwnershipPercentage(
                unitId: (int) $data['unit_id'],
                percentage: $data['ownership_percentage'] ?? null
            );

            if (($data['is_primary'] ?? false) === true) {
                $this->clearPrimaryOwnerships(
                    (int) $data['unit_id']
                );
            }

            $data['created_by'] = $actor->getKey();

            return UnitOwnership::query()->create($data);
        });
    }

    public function update(
        UnitOwnership $ownership,
        array $data
    ): UnitOwnership {
        return DB::transaction(function () use ($ownership, $data): UnitOwnership {
            if (array_key_exists('ownership_percentage', $data)) {
                $this->validateOwnershipPercentage(
                    unitId: (int) $ownership->unit_id,
                    percentage: $data['ownership_percentage'],
                    ignoreOwnershipId: $ownership->getKey()
                );
            }

            if (($data['is_primary'] ?? false) === true) {
                $this->clearPrimaryOwnerships(
                    (int) $ownership->unit_id,
                    $ownership->getKey()
                );
            }

            $ownership->update($data);

            return $ownership->refresh();
        });
    }

    public function end(
        UnitOwnership $ownership,
        User $actor,
        ?string $endsAt = null
    ): UnitOwnership {
        return DB::transaction(function () use (
            $ownership,
            $actor,
            $endsAt
        ): UnitOwnership {
            if (! $ownership->is_active) {
                return $ownership->refresh();
            }

            $endDate = $endsAt ?? now()->toDateString();

            if (
                $ownership->starts_at
                && $endDate < $ownership->starts_at->toDateString()
            ) {
                throw ValidationException::withMessages([
                    'ends_at' => [
                        'The end date cannot be before the ownership start date.',
                    ],
                ]);
            }

            $ownership->update([
                'ends_at' => $endDate,
                'is_active' => false,
                'is_primary' => false,
                'ended_by' => $actor->getKey(),
            ]);

            return $ownership->refresh();
        });
    }

    private function clearPrimaryOwnerships(
        int $unitId,
        ?int $exceptId = null
    ): void {
        UnitOwnership::query()
            ->where('unit_id', $unitId)
            ->where('is_primary', true)
            ->where('is_active', true)
            ->when(
                $exceptId !== null,
                fn ($query) => $query->whereKeyNot($exceptId)
            )
            ->update([
                'is_primary' => false,
            ]);
    }

    private function validateOwnershipPercentage(
        int $unitId,
        mixed $percentage,
        ?int $ignoreOwnershipId = null
    ): void {
        if ($percentage === null) {
            return;
        }

        $currentTotal = UnitOwnership::query()
            ->where('unit_id', $unitId)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now()->toDateString());
            })
            ->when(
                $ignoreOwnershipId !== null,
                fn ($query) => $query->whereKeyNot($ignoreOwnershipId)
            )
            ->sum('ownership_percentage');

        if (
            (float) $currentTotal
            + (float) $percentage
            > 100.0
        ) {
            throw ValidationException::withMessages([
                'ownership_percentage' => [
                    'The total active ownership percentage for the unit cannot exceed 100.',
                ],
            ]);
        }
    }
}
