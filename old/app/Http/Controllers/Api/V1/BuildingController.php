<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Building\CreateBuilding;
use App\Actions\Building\UpdateBuilding;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBuildingRequest;
use App\Http\Requests\UpdateBuildingRequest;
use App\Http\Resources\V1\BuildingResource;
use App\Models\Building;
use App\Models\Complex;
use App\Support\Authorization\BuildingVisibilityQuery;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Buildings',
    description: 'Building management'
)]
class BuildingController extends Controller
{
    #[OA\Get(
        path: '/buildings',
        operationId: 'buildingsIndex',
        summary: 'List buildings',
        description: 'Returns only buildings visible to the authenticated user based on global, complex or building scoped assignments.',
        security: [
            ['sanctum' => []],
        ],
        tags: ['Buildings'],
        parameters: [
            new OA\Parameter(
                name: 'complex_id',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'integer'
                )
            ),

            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'string'
                )
            ),

            new OA\Parameter(
                name: 'is_active',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'boolean'
                )
            ),

            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'integer',
                    default: 20,
                    minimum: 1,
                    maximum: 100
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Building list'
            ),

            new OA\Response(
                response: 401,
                description: 'Unauthenticated'
            ),

            new OA\Response(
                response: 403,
                description: 'Forbidden'
            ),
        ]
    )]
    public function index(
        Request $request,
        BuildingVisibilityQuery $visibility
    ): AnonymousResourceCollection {
        $query = Building::query()
            ->with(
                'complex:id,code,title'
            )
            ->withCount([
                'blocks',
                'buildingFacilities',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Authorization Scope
        |--------------------------------------------------------------------------
        |
        | The visibility restriction is applied before user filters
        | and before pagination to prevent cross-scope data leakage.
        |
        */

        $visibility->apply(
            $query,
            $request->user()
        );

        /*
        |--------------------------------------------------------------------------
        | Complex Filter
        |--------------------------------------------------------------------------
        */

        if ($complexId = $request->integer('complex_id')) {
            $query->where(
                'complex_id',
                $complexId
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if (
            $search = trim(
                (string) $request->query('search')
            )
        ) {
            $query->where(
                function (Builder $query) use ($search): void {
                    $query
                        ->where(
                            'title',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'code',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'building_number',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Active Filter
        |--------------------------------------------------------------------------
        */

        if ($request->has('is_active')) {
            $query->where(
                'is_active',
                $request->boolean('is_active')
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        $perPage = min(
            max(
                $request->integer(
                    'per_page',
                    20
                ),
                1
            ),
            100
        );

        $buildings = $query
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return BuildingResource::collection(
            $buildings
        );
    }

    #[OA\Post(
        path: '/buildings',
        operationId: 'buildingsStore',
        summary: 'Create building',
        description: 'Creates a building only when the authenticated user has buildings.create permission in the requested complex scope or globally.',
        security: [
            ['sanctum' => []],
        ],
        tags: ['Buildings'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: '#/components/schemas/BuildingRequest'
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Building created'
            ),

            new OA\Response(
                response: 403,
                description: 'Forbidden'
            ),

            new OA\Response(
                response: 404,
                description: 'Complex not found'
            ),

            new OA\Response(
                response: 422,
                description: 'Validation error'
            ),
        ]
    )]
    public function store(
        StoreBuildingRequest $request,
        CreateBuilding $action,
        PermissionChecker $permissions
    ) {
        $validated = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Resolve Parent Scope
        |--------------------------------------------------------------------------
        */

        $complex = Complex::query()
            ->findOrFail(
                $validated['complex_id']
            );

        /*
        |--------------------------------------------------------------------------
        | Scoped Authorization
        |--------------------------------------------------------------------------
        */

        abort_unless(
            $permissions->allows(
                $request->user(),
                'buildings.create',
                $complex
            ),
            403
        );

        /*
        |--------------------------------------------------------------------------
        | Create
        |--------------------------------------------------------------------------
        */

        $building = $action->execute(
            $validated
        );

        $building->load(
            'complex:id,code,title'
        );

        $building->loadCount([
            'blocks',
            'buildingFacilities',
        ]);

        return (new BuildingResource($building))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/buildings/{building}',
        operationId: 'buildingsShow',
        summary: 'Show building',
        security: [
            ['sanctum' => []],
        ],
        tags: ['Buildings'],
        parameters: [
            new OA\Parameter(
                name: 'building',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'integer'
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Building details'
            ),

            new OA\Response(
                response: 403,
                description: 'Forbidden'
            ),

            new OA\Response(
                response: 404,
                description: 'Building not found'
            ),
        ]
    )]
    public function show(
        Building $building
    ): BuildingResource {
        $this->authorize(
            'view',
            $building
        );

        $building->load(
            'complex:id,code,title'
        );

        $building->loadCount([
            'blocks',
            'buildingFacilities',
        ]);

        return new BuildingResource(
            $building
        );
    }

    #[OA\Put(
        path: '/buildings/{building}',
        operationId: 'buildingsUpdate',
        summary: 'Update building',
        security: [
            ['sanctum' => []],
        ],
        tags: ['Buildings'],
        parameters: [
            new OA\Parameter(
                name: 'building',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'integer'
                )
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: '#/components/schemas/BuildingRequest'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Building updated'
            ),

            new OA\Response(
                response: 403,
                description: 'Forbidden'
            ),

            new OA\Response(
                response: 404,
                description: 'Building not found'
            ),

            new OA\Response(
                response: 422,
                description: 'Validation error'
            ),
        ]
    )]
    public function update(
        UpdateBuildingRequest $request,
        Building $building,
        UpdateBuilding $action
    ): BuildingResource {
        $this->authorize(
            'update',
            $building
        );

        $building = $action->execute(
            $building,
            $request->validated()
        );

        $building->load(
            'complex:id,code,title'
        );

        $building->loadCount([
            'blocks',
            'buildingFacilities',
        ]);

        return new BuildingResource(
            $building
        );
    }

    #[OA\Delete(
        path: '/buildings/{building}',
        operationId: 'buildingsDestroy',
        summary: 'Delete building',
        security: [
            ['sanctum' => []],
        ],
        tags: ['Buildings'],
        parameters: [
            new OA\Parameter(
                name: 'building',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'integer'
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Building deleted'
            ),

            new OA\Response(
                response: 403,
                description: 'Forbidden'
            ),

            new OA\Response(
                response: 404,
                description: 'Building not found'
            ),
        ]
    )]
    public function destroy(
        Building $building
    ): Response {
        $this->authorize(
            'delete',
            $building
        );

        $building->delete();

        return response()->noContent();
    }
}
