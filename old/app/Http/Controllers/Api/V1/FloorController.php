<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFloorRequest;
use App\Http\Requests\UpdateFloorRequest;
use App\Http\Resources\V1\FloorResource;
use App\Models\Block;
use App\Models\Floor;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class FloorController extends Controller
{
    public function index(
        Request $request,
        Block $block,
        PermissionChecker $permissions
    ): AnonymousResourceCollection {
        $block->loadMissing('building');

        abort_unless(
            $block->building
            && $permissions->allows(
                $request->user(),
                'floors.view',
                $block->building
            ),
            403
        );

        $query = $block->floors()
            ->withCount('units');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function (Builder $query) use ($search): void {
                $query
                    ->where('title', 'like', "%{$search}%");

                if (is_numeric($search)) {
                    $query->orWhere(
                        'floor_number',
                        (int) $search
                    );
                }
            });
        }

        $perPage = min(
            max($request->integer('per_page', 20), 1),
            100
        );

        $floors = $query
            ->orderBy('sort_order')
            ->orderBy('floor_number')
            ->paginate($perPage)
            ->withQueryString();

        return FloorResource::collection($floors);
    }

    public function store(
        StoreFloorRequest $request,
        Block $block,
        PermissionChecker $permissions
    ) {
        $block->loadMissing('building');

        abort_unless(
            $block->building
            && $permissions->allows(
                $request->user(),
                'floors.create',
                $block->building
            ),
            403
        );

        $floor = DB::transaction(
            fn (): Floor => $block->floors()->create(
                $request->validated()
            )
        );

        $floor->loadCount('units');

        return (new FloorResource($floor))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Floor $floor): FloorResource
    {
        $this->authorize('view', $floor);

        $floor->load('block.building:id,complex_id,code,title');
        $floor->loadCount('units');

        return new FloorResource($floor);
    }

    public function update(
        UpdateFloorRequest $request,
        Floor $floor
    ): FloorResource {
        $this->authorize('update', $floor);

        $floor = DB::transaction(function () use ($request, $floor): Floor {
            $floor->update($request->validated());

            return $floor->refresh();
        });

        $floor->load('block.building:id,complex_id,code,title');
        $floor->loadCount('units');

        return new FloorResource($floor);
    }

    public function destroy(Floor $floor): Response
    {
        $this->authorize('delete', $floor);

        $floor->delete();

        return response()->noContent();
    }
}
