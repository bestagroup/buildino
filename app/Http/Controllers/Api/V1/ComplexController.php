<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Complex\CreateComplex;
use App\Actions\Complex\UpdateComplex;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComplexRequest;
use App\Http\Requests\UpdateComplexRequest;
use App\Http\Resources\V1\ComplexResource;
use App\Models\Complex;
use App\Support\Authorization\ComplexVisibilityQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Complexes',
    description: 'Complex management'
)]
class ComplexController extends Controller
{
    #[OA\Get(
        path: '/complexes',
        operationId: 'complexesIndex',
        summary: 'List complexes',
        security: [
            ['sanctum' => []],
        ],
        tags: ['Complexes'],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),

            new OA\Parameter(
                name: 'province',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),

            new OA\Parameter(
                name: 'city',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),

            new OA\Parameter(
                name: 'is_active',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean')
            ),

            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'integer',
                    default: 20,
                    maximum: 100
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Complex list'
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
        ComplexVisibilityQuery $visibility
    ): AnonymousResourceCollection {
        $query = Complex::query()
            ->withCount('buildings');

        /*
        |--------------------------------------------------------------------------
        | Authorization Scope
        |--------------------------------------------------------------------------
        |
        | Global users see all complexes. Complex-scoped managers see only
        | their assigned complexes. The restriction is applied before filters
        | and pagination, preventing cross-scope data leakage.
        |
        */

        $visibility->apply(
            $query,
            $request->user()
        );

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($query) use ($search): void {
                $query
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('province', 'like', "%{$search}%");
            });
        }

        if ($province = $request->query('province')) {
            $query->where(
                'province',
                $province
            );
        }

        if ($city = $request->query('city')) {
            $query->where(
                'city',
                $city
            );
        }

        if ($request->has('is_active')) {
            $query->where(
                'is_active',
                $request->boolean('is_active')
            );
        }

        $perPage = min(
            max(
                (int) $request->integer('per_page', 20),
                1
            ),
            100
        );

        $items = $query
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return ComplexResource::collection(
            $items
        );
    }

    #[OA\Post(
        path: '/complexes',
        operationId: 'complexesStore',
        summary: 'Create complex',
        security: [
            ['sanctum' => []],
        ],
        tags: ['Complexes'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: '#/components/schemas/ComplexRequest'
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Complex created',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/Complex'
                )
            ),

            new OA\Response(
                response: 422,
                description: 'Validation error'
            ),

            new OA\Response(
                response: 403,
                description: 'Forbidden'
            ),
        ]
    )]
    public function store(
        StoreComplexRequest $request,
        CreateComplex $action
    ) {
        $this->authorize(
            'create',
            Complex::class
        );

        $complex = $action->execute(
            $request->validated()
        );

        return (new ComplexResource($complex))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/complexes/{complex}',
        operationId: 'complexesShow',
        summary: 'Show complex',
        security: [
            ['sanctum' => []],
        ],
        tags: ['Complexes'],
        parameters: [
            new OA\Parameter(
                name: 'complex',
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
                description: 'Complex details'
            ),

            new OA\Response(
                response: 404,
                description: 'Complex not found'
            ),

            new OA\Response(
                response: 403,
                description: 'Forbidden'
            ),
        ]
    )]
    public function show(
        Complex $complex
    ): ComplexResource {
        $this->authorize(
            'view',
            $complex
        );

        $complex->loadCount(
            'buildings'
        );

        return new ComplexResource(
            $complex
        );
    }

    #[OA\Put(
        path: '/complexes/{complex}',
        operationId: 'complexesUpdate',
        summary: 'Update complex',
        security: [
            ['sanctum' => []],
        ],
        tags: ['Complexes'],
        parameters: [
            new OA\Parameter(
                name: 'complex',
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
                ref: '#/components/schemas/ComplexRequest'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Complex updated'
            ),

            new OA\Response(
                response: 422,
                description: 'Validation error'
            ),

            new OA\Response(
                response: 404,
                description: 'Complex not found'
            ),
        ]
    )]
    public function update(
        UpdateComplexRequest $request,
        Complex $complex,
        UpdateComplex $action
    ): ComplexResource {
        $this->authorize(
            'update',
            $complex
        );

        $complex = $action->execute(
            $complex,
            $request->validated()
        );

        $complex->loadCount(
            'buildings'
        );

        return new ComplexResource(
            $complex
        );
    }

    #[OA\Delete(
        path: '/complexes/{complex}',
        operationId: 'complexesDestroy',
        summary: 'Delete complex',
        security: [
            ['sanctum' => []],
        ],
        tags: ['Complexes'],
        parameters: [
            new OA\Parameter(
                name: 'complex',
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
                description: 'Complex deleted'
            ),

            new OA\Response(
                response: 404,
                description: 'Complex not found'
            ),

            new OA\Response(
                response: 403,
                description: 'Forbidden'
            ),
        ]
    )]
    public function destroy(
        Complex $complex
    ): Response {
        $this->authorize(
            'delete',
            $complex
        );

        $complex->delete();

        return response()->noContent();
    }
}
