<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;

final class GuestDirectoryService
{
    public function resolveForUnit(
        Unit $unit,
        array $data
    ): Guest {
        $data = $this->normalize(
            $data
        );

        $guest = $this->findExistingForUnit(
            $unit,
            $data
        );

        if (! $guest) {
            return Guest::query()->create(
                $data
            );
        }

        /*
         * Reuse is intentionally limited to the same unit.
         * This prevents a resident from discovering or mutating
         * a guest profile belonging only to another building/unit.
         */
        $guest->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],

            'mobile' => $data['mobile']
                ?? $guest->mobile,

            'national_code' => $data['national_code']
                ?? $guest->national_code,

            'vehicle_plate' => $data['vehicle_plate']
                ?? $guest->vehicle_plate,
        ]);

        return $guest->refresh();
    }

    private function findExistingForUnit(
        Unit $unit,
        array $data
    ): ?Guest {
        if (
            blank($data['national_code'] ?? null)
            && blank($data['mobile'] ?? null)
        ) {
            return null;
        }

        return Guest::query()
            ->whereHas(
                'guestVisits',
                fn (Builder $query) => $query->where(
                    'unit_id',
                    $unit->getKey()
                )
            )
            ->where(function (Builder $query) use ($data): void {
                $hasCondition = false;

                if (filled($data['national_code'] ?? null)) {
                    $query->where(
                        'national_code',
                        $data['national_code']
                    );

                    $hasCondition = true;
                }

                if (filled($data['mobile'] ?? null)) {
                    if ($hasCondition) {
                        $query->orWhere(
                            'mobile',
                            $data['mobile']
                        );
                    } else {
                        $query->where(
                            'mobile',
                            $data['mobile']
                        );
                    }
                }
            })
            ->latest('id')
            ->first();
    }

    private function normalize(
        array $data
    ): array {
        $data['first_name'] = trim(
            (string) $data['first_name']
        );

        $data['last_name'] = trim(
            (string) $data['last_name']
        );

        foreach (
            [
                'mobile',
                'national_code',
                'vehicle_plate',
            ] as $key
        ) {
            if (array_key_exists($key, $data)) {
                $value = trim(
                    (string) $data[$key]
                );

                $data[$key] = $value !== ''
                    ? $value
                    : null;
            }
        }

        return $data;
    }
}
