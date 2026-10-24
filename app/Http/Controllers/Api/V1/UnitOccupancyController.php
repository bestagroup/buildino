<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\UnitOccupancy\AssignUnitOccupancy;
use App\Actions\UnitOccupancy\EndUnitOccupancy;
use App\Http\Controllers\Controller;
use App\Http\Requests\EndUnitOccupancyRequest;
use App\Http\Requests\StoreUnitOccupancyRequest;
use App\Http\Requests\UpdateUnitOccupancyRequest;
use App\Http\Resources\V1\UnitOccupancyResource;
use App\Models\Unit;
use App\Models\UnitOccupancy;
use App\Services\OccupancyService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UnitOccupancyController extends Controller
{
    public function index(
        Request $request,
        Unit $unit,
        PermissionChecker $permissions
    ): AnonymousResourceCollection {
        $building = $this->resolveBuilding($unit);

        abort_unless(
            $building
            && $permissions->allows(
                $request->user(),
                'unit-occupancies.view',
                $building
            ),
            403
        );

        $query = $unit->unitOccupancies()
            ->with('user:id,first_name,last_name,mobile,email');

        if ($request->has('is_active')) {
            $query->where(
                'is_active',
                $request->boolean('is_active')
            );
        }

        if ($type = $request->query('occupancy_type')) {
            $query->where(
                'occupancy_type',
                $type
            );
        }

        $perPage = min(
            max($request->integer('per_page', 20), 1),
            100
        );

        return UnitOccupancyResource::collection(
            $query
                ->orderByDesc('is_active')
                ->orderByDesc('is_primary')
                ->latest('id')
                ->paginate($perPage)
                ->withQueryString()
        );
    }

    public function store(
        StoreUnitOccupancyRequest $request,
        Unit $unit,
        AssignUnitOccupancy $action,
        PermissionChecker $permissions
    ) {
        $building = $this->resolveBuilding($unit);

        abort_unless(
            $building
            && $permissions->allows(
                $request->user(),
                'unit-occupancies.create',
                $building
            ),
            403
        );

        $occupancy = $action->execute(
            [
                ...$request->validated(),
                'unit_id' => $unit->getKey(),
            ],
            $request->user()
        );

        $occupancy->load([
            'user:id,first_name,last_name,mobile,email',
            'unit:id,floor_id,unit_number,title',
        ]);

        return (new UnitOccupancyResource($occupancy))
            ->response()
            ->setStatusCode(201);
    }

    public function show(
        UnitOccupancy $unitOccupancy
    ): UnitOccupancyResource {
        $this->authorize(
            'view',
            $unitOccupancy
        );

        $unitOccupancy->load([
            'user:id,first_name,last_name,mobile,email',
            'unit:id,floor_id,unit_number,title',
        ]);

        return new UnitOccupancyResource(
            $unitOccupancy
        );
    }

    public function update(
        UpdateUnitOccupancyRequest $request,
        UnitOccupancy $unitOccupancy,
        OccupancyService $service
    ): UnitOccupancyResource {
        $this->authorize(
            'update',
            $unitOccupancy
        );

        $unitOccupancy = $service->update(
            $unitOccupancy,
            $request->validated()
        );

        $unitOccupancy->load([
            'user:id,first_name,last_name,mobile,email',
            'unit:id,floor_id,unit_number,title',
        ]);

        return new UnitOccupancyResource(
            $unitOccupancy
        );
    }

    public function end(
        EndUnitOccupancyRequest $request,
        UnitOccupancy $unitOccupancy,
        EndUnitOccupancy $action
    ): UnitOccupancyResource {
        $this->authorize(
            'update',
            $unitOccupancy
        );

        $unitOccupancy = $action->execute(
            $unitOccupancy,
            $request->user(),
            $request->validated('ends_at')
        );

        $unitOccupancy->load([
            'user:id,first_name,last_name,mobile,email',
            'unit:id,floor_id,unit_number,title',
        ]);

        return new UnitOccupancyResource(
            $unitOccupancy
        );
    }

    private function resolveBuilding(Unit $unit)
    {
        $unit->loadMissing(
            'floor.block.building'
        );

        return $unit->floor?->block?->building;
    }
}
