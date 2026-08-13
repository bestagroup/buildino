<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EndUnitOwnershipRequest;
use App\Http\Requests\StoreUnitOwnershipRequest;
use App\Http\Requests\UpdateUnitOwnershipRequest;
use App\Http\Resources\V1\UnitOwnershipResource;
use App\Models\Unit;
use App\Models\UnitOwnership;
use App\Services\UnitOwnershipService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UnitOwnershipController extends Controller
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
                'unit-ownerships.view',
                $building
            ),
            403
        );

        $query = $unit->unitOwnerships()
            ->with('user:id,first_name,last_name,mobile,email');

        if ($request->has('is_active')) {
            $query->where(
                'is_active',
                $request->boolean('is_active')
            );
        }

        $perPage = min(
            max($request->integer('per_page', 20), 1),
            100
        );

        return UnitOwnershipResource::collection(
            $query
                ->orderByDesc('is_active')
                ->orderByDesc('is_primary')
                ->latest('id')
                ->paginate($perPage)
                ->withQueryString()
        );
    }

    public function store(
        StoreUnitOwnershipRequest $request,
        Unit $unit,
        UnitOwnershipService $service,
        PermissionChecker $permissions
    ) {
        $building = $this->resolveBuilding($unit);

        abort_unless(
            $building
            && $permissions->allows(
                $request->user(),
                'unit-ownerships.create',
                $building
            ),
            403
        );

        $ownership = $service->assign(
            [
                ...$request->validated(),
                'unit_id' => $unit->getKey(),
            ],
            $request->user()
        );

        $ownership->load([
            'user:id,first_name,last_name,mobile,email',
            'unit:id,floor_id,unit_number,title',
        ]);

        return (new UnitOwnershipResource($ownership))
            ->response()
            ->setStatusCode(201);
    }

    public function show(
        UnitOwnership $unitOwnership
    ): UnitOwnershipResource {
        $this->authorize(
            'view',
            $unitOwnership
        );

        $unitOwnership->load([
            'user:id,first_name,last_name,mobile,email',
            'unit:id,floor_id,unit_number,title',
        ]);

        return new UnitOwnershipResource(
            $unitOwnership
        );
    }

    public function update(
        UpdateUnitOwnershipRequest $request,
        UnitOwnership $unitOwnership,
        UnitOwnershipService $service
    ): UnitOwnershipResource {
        $this->authorize(
            'update',
            $unitOwnership
        );

        $unitOwnership = $service->update(
            $unitOwnership,
            $request->validated()
        );

        $unitOwnership->load([
            'user:id,first_name,last_name,mobile,email',
            'unit:id,floor_id,unit_number,title',
        ]);

        return new UnitOwnershipResource(
            $unitOwnership
        );
    }

    public function end(
        EndUnitOwnershipRequest $request,
        UnitOwnership $unitOwnership,
        UnitOwnershipService $service
    ): UnitOwnershipResource {
        $this->authorize(
            'update',
            $unitOwnership
        );

        $unitOwnership = $service->end(
            $unitOwnership,
            $request->user(),
            $request->validated('ends_at')
        );

        $unitOwnership->load([
            'user:id,first_name,last_name,mobile,email',
            'unit:id,floor_id,unit_number,title',
        ]);

        return new UnitOwnershipResource(
            $unitOwnership
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
