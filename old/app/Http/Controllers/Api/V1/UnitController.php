<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Unit\CreateUnit;
use App\Actions\Unit\UpdateUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUnitRequest;
use App\Http\Requests\UpdateUnitRequest;
use App\Http\Resources\V1\UnitResource;
use App\Models\Floor;
use App\Models\Unit;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class UnitController extends Controller
{
    public function index(
        Request $request,
        Floor $floor,
        PermissionChecker $permissions
    ): AnonymousResourceCollection {
        $floor->loadMissing('block.building');

        $building = $floor->block?->building;

        abort_unless(
            $building
            && $permissions->allows(
                $request->user(),
                'units.view',
                $building
            ),
            403
        );

        $query = $floor->units();

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function (Builder $query) use ($search): void {
                $query
                    ->where('unit_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        if ($usageType = $request->query('usage_type')) {
            $query->where('usage_type', $usageType);
        }

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

        $units = $query
            ->orderBy('unit_number')
            ->paginate($perPage)
            ->withQueryString();

        return UnitResource::collection($units);
    }

    public function store(
        StoreUnitRequest $request,
        Floor $floor,
        CreateUnit $action,
        PermissionChecker $permissions
    ) {
        $floor->loadMissing('block.building');

        $building = $floor->block?->building;

        abort_unless(
            $building
            && $permissions->allows(
                $request->user(),
                'units.create',
                $building
            ),
            403
        );

        $unit = $action->execute([
            ...$request->validated(),
            'floor_id' => $floor->getKey(),
        ]);

        $unit->load('floor.block.building:id,complex_id,code,title');

        return (new UnitResource($unit))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Unit $unit): UnitResource
    {
        $this->authorize('view', $unit);

        $unit->load([
            'floor.block.building:id,complex_id,code,title',
            'unitOwnerships',
            'unitOccupancies',
        ]);

        return new UnitResource($unit);
    }

    public function update(
        UpdateUnitRequest $request,
        Unit $unit,
        UpdateUnit $action
    ): UnitResource {
        $this->authorize('update', $unit);

        $unit = $action->execute(
            $unit,
            $request->validated()
        );

        $unit->load('floor.block.building:id,complex_id,code,title');

        return new UnitResource($unit);
    }

    public function destroy(Unit $unit): Response
    {
        $this->authorize('delete', $unit);

        $unit->delete();

        return response()->noContent();
    }
}
