<?php

namespace App\Services\Security;

use App\Models\Building;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class BuildingContextResolver
{
    public function resolve(Request $request): ?Building
    {
        $building = $this->routeModel($request, 'building');

        if ($building instanceof Building) {
            return $building;
        }

        if (is_numeric($building)) {
            return Building::query()->find((int) $building);
        }

        if ($request->filled('building_id')) {
            return Building::query()->find((int) $request->input('building_id'));
        }

        /*
         * Resolve any bound domain model back to its owning building.
         * This keeps entitlement enforcement consistent as new nested
         * resources are added without duplicating route-specific logic.
         */
        $route = $request->route();

        if ($route) {
            foreach ($route->parameters() as $parameter) {
                if ($parameter instanceof Model) {
                    $resolved = $this->buildingFromModel($parameter);

                    if ($resolved) {
                        return $resolved;
                    }
                }
            }
        }

        $unit = $this->routeModel($request, 'unit');

        if ($unit instanceof Unit) {
            return $this->buildingFromUnit($unit);
        }

        if (is_numeric($unit)) {
            $unit = Unit::query()
                ->with('floor.block.building')
                ->find((int) $unit);

            return $unit ? $this->buildingFromUnit($unit) : null;
        }

        return null;
    }

    private function routeModel(
        Request $request,
        string $key
    ): Model|int|string|null {
        return $request->route($key);
    }

    private function buildingFromModel(
        Model $model,
        int $depth = 0
    ): ?Building {
        if ($model instanceof Building) {
            return $model;
        }

        if ($model instanceof Unit) {
            return $this->buildingFromUnit($model);
        }

        if ($depth >= 4) {
            return null;
        }

        $buildingId = $model->getAttribute('building_id');

        if (is_numeric($buildingId)) {
            return Building::query()->find((int) $buildingId);
        }

        foreach ([
            'building',
            'unit',
            'buildingFacility',
            'facility',
            'block',
            'floor',
            'loyaltyReward',
        ] as $relation) {
            if (! method_exists($model, $relation)) {
                continue;
            }

            $related = $model->getRelationValue($relation);

            if ($related instanceof Model) {
                $building = $this->buildingFromModel(
                    $related,
                    $depth + 1
                );

                if ($building) {
                    return $building;
                }
            }
        }

        return null;
    }

    private function buildingFromUnit(Unit $unit): ?Building
    {
        $unit->loadMissing('floor.block.building');

        return $unit->floor?->block?->building;
    }
}
