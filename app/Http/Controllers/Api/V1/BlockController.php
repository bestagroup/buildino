<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBlockRequest;
use App\Http\Requests\UpdateBlockRequest;
use App\Http\Resources\V1\BlockResource;
use App\Models\Block;
use App\Models\Building;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class BlockController extends Controller
{
    public function index(
        Request $request,
        Building $building,
        PermissionChecker $permissions
    ): AnonymousResourceCollection {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'blocks.view',
                $building
            ),
            403
        );

        $query = $building->blocks()
            ->withCount('floors');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(
                fn (Builder $query) => $query->where(
                    'title',
                    'like',
                    "%{$search}%"
                )
            );
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

        $blocks = $query
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return BlockResource::collection($blocks);
    }

    public function store(
        StoreBlockRequest $request,
        Building $building,
        PermissionChecker $permissions
    ) {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'blocks.create',
                $building
            ),
            403
        );

        $block = DB::transaction(
            fn (): Block => $building->blocks()->create(
                $request->validated()
            )
        );

        $block->loadCount('floors');

        return (new BlockResource($block))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Block $block): BlockResource
    {
        $this->authorize('view', $block);

        $block->load('building:id,complex_id,code,title');
        $block->loadCount('floors');

        return new BlockResource($block);
    }

    public function update(
        UpdateBlockRequest $request,
        Block $block
    ): BlockResource {
        $this->authorize('update', $block);

        $block = DB::transaction(function () use ($request, $block): Block {
            $block->update($request->validated());

            return $block->refresh();
        });

        $block->load('building:id,complex_id,code,title');
        $block->loadCount('floors');

        return new BlockResource($block);
    }

    public function destroy(Block $block): Response
    {
        $this->authorize('delete', $block);

        $block->delete();

        return response()->noContent();
    }
}
