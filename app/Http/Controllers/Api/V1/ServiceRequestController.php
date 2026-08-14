<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceRequestRequest;
use App\Http\Requests\UpdateServiceRequestRequest;
use App\Models\ServiceRequest;
use App\Services\ServiceMarketplace\ServiceRequestAccessService;
use App\Services\ServiceMarketplace\ServiceRequestCrudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceRequestController extends Controller
{
    public function index(
        Request $request,
        ServiceRequestAccessService $access
    ): JsonResponse {
        $this->authorize('viewAny', ServiceRequest::class);

        $items = $access
            ->visibleQuery($request->user())
            ->with([
                'requestedBy:id,first_name,last_name,mobile',
                'assignedTo:id,first_name,last_name,mobile',
            ])
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where(
                    'status',
                    $request->string('status')->toString()
                )
            )
            ->latest('id')
            ->paginate(
                min(max((int) $request->integer('per_page', 20), 1), 100)
            );

        return response()->json($items);
    }

    public function store(
        StoreServiceRequestRequest $request,
        ServiceRequestCrudService $service
    ): JsonResponse {
        $this->authorize('create', ServiceRequest::class);

        $model = $service->create(
            $request->user(),
            $request->validated()
        );

        return response()->json(['data' => $model], 201);
    }

    public function show(ServiceRequest $serviceRequest): JsonResponse
    {
        $this->authorize('view', $serviceRequest);

        return response()->json([
            'data' => $serviceRequest->load([
                'requestedBy:id,first_name,last_name,mobile',
                'assignedTo:id,first_name,last_name,mobile',
                'quotes',
                'walletPayment',
            ]),
        ]);
    }

    public function update(
        UpdateServiceRequestRequest $request,
        ServiceRequest $serviceRequest,
        ServiceRequestCrudService $service
    ): JsonResponse {
        $this->authorize('update', $serviceRequest);

        return response()->json([
            'data' => $service->update(
                $serviceRequest,
                $request->user(),
                $request->validated()
            ),
        ]);
    }

    public function destroy(ServiceRequest $serviceRequest): JsonResponse
    {
        $this->authorize('delete', $serviceRequest);
        $serviceRequest->delete();

        return response()->json(status: 204);
    }
}
