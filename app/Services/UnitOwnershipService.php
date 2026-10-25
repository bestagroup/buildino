<?php

namespace App\Services;

use App\Models\Unit;
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
            Unit::query()
                ->whereKey((int) $data['unit_id'])
                ->lockForUpdate()
                ->firstOrFail();

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
            Unit::query()
                ->whereKey((int) $ownership->unit_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (isset($data['user_id']) && (int) $data['user_id'] !== (int) $ownership->user_id) {
                $duplicate = UnitOwnership::query()
                    ->where('unit_id', $ownership->unit_id)
                    ->where('user_id', $data['user_id'])
                    ->whereKeyNot($ownership->getKey())
                    ->where('is_active', true)
                    ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString()))
                    ->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'user_id' => ['این کاربر هم‌اکنون مالک این واحد است؛ مالکیت فعلی او را ویرایش کنید.'],
                    ]);
                }
            }

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
            Unit::query()
                ->whereKey((int) $ownership->unit_id)
                ->lockForUpdate()
                ->firstOrFail();

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
