<?php

namespace App\Services\Mobile;

use App\Http\Resources\V1\AuthUserResource;
use App\Models\Unit;
use App\Models\UnitOccupancy;
use App\Models\UnitOwnership;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class MobileBootstrapService
{
    public function build(User $user, Request $request): array
    {
        $relationships = $this->relationships($user);

        $contexts = $relationships
            ->map(fn (array $relationship): array => $this->context($relationship))
            ->values()
            ->all();

        $personas = [];

        if ($relationships->contains(fn (array $relationship): bool => $relationship['owner'])) {
            $personas[] = 'owner';
        }

        if ($relationships->contains(fn (array $relationship): bool => $relationship['occupant'])) {
            $personas[] = 'occupant';
        }

        return [
            'user' => (new AuthUserResource($user))->resolve($request),
            'personas' => $personas,
            'contexts' => $contexts,
            'suggested_context' => $contexts[0]['id'] ?? null,
        ];
    }

    /**
     * Return one merged, current relationship record per unit.
     *
     * @return Collection<int, array{unit: Unit, owner: bool, occupant: bool}>
     */
    private function relationships(User $user): Collection
    {
        $today = now()->toDateString();
        $with = ['unit.floor.block.building'];
        $byUnit = collect();

        $ownerships = UnitOwnership::query()
            ->with($with)
            ->where('user_id', $user->getKey())
            ->where('is_active', true)
            ->whereDate('starts_at', '<=', $today)
            ->where(fn (Builder $query) => $query
                ->whereNull('ends_at')
                ->orWhereDate('ends_at', '>=', $today))
            ->get();

        foreach ($ownerships as $ownership) {
            if (! $ownership->unit) {
                continue;
            }

            $byUnit->put($ownership->unit_id, [
                'unit' => $ownership->unit,
                'owner' => true,
                'occupant' => false,
            ]);
        }

        $occupancies = UnitOccupancy::query()
            ->with($with)
            ->where('user_id', $user->getKey())
            ->where('is_active', true)
            ->whereDate('starts_at', '<=', $today)
            ->where(fn (Builder $query) => $query
                ->whereNull('ends_at')
                ->orWhereDate('ends_at', '>=', $today))
            ->get();

        foreach ($occupancies as $occupancy) {
            if (! $occupancy->unit) {
                continue;
            }

            $existing = $byUnit->get($occupancy->unit_id, [
                'unit' => $occupancy->unit,
                'owner' => false,
                'occupant' => false,
            ]);

            $existing['occupant'] = true;
            $byUnit->put($occupancy->unit_id, $existing);
        }

        return $byUnit->sortKeys()->values();
    }

    /**
     * @param  array{unit: Unit, owner: bool, occupant: bool}  $relationship
     */
    private function context(array $relationship): array
    {
        $unit = $relationship['unit'];
        $building = $unit->floor?->block?->building;

        return [
            'id' => 'unit-'.$unit->getKey(),
            'building' => [
                'id' => $building?->getKey(),
                'code' => $building?->code,
                'title' => $building?->title,
            ],
            'unit' => [
                'id' => $unit->getKey(),
                'unit_number' => $unit->unit_number,
                'title' => $unit->title,
            ],
            'relationships' => [
                'owner' => $relationship['owner'],
                'occupant' => $relationship['occupant'],
            ],
            'capabilities' => [
                'charges.view' => true,
                'wallet.view' => true,
            ],
        ];
    }
}
