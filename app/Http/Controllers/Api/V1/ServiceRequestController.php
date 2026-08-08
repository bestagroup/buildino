<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ServiceRequest\CreateServiceRequest;
use App\Actions\ServiceRequest\UpdateServiceRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceRequestRequest;
use App\Http\Requests\UpdateServiceRequestRequest;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceRequest::class);

        $items = ServiceRequest::query()
            ->latest('id')
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 100));

        return response()->json($items);
    }

    public function store(StoreServiceRequestRequest $request, CreateServiceRequest $action): JsonResponse
    {
        $this->authorize('create', ServiceRequest::class);
        $model = $action->execute($request->validated());

        return response()->json(['data' => $model], 201);
    }

    public function show(ServiceRequest $model): JsonResponse
    {
        $this->authorize('view', $model);
        return response()->json(['data' => $model]);
    }

    public function update(UpdateServiceRequestRequest $request, ServiceRequest $model, UpdateServiceRequest $action): JsonResponse
    {
        $this->authorize('update', $model);
        return response()->json(['data' => $action->execute($model, $request->validated())]);
    }

    public function destroy(ServiceRequest $model): JsonResponse
    {
        $this->authorize('delete', $model);
        $model->delete();
        return response()->json(status: 204);
    }
}
