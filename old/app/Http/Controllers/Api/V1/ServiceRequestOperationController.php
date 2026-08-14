<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ServiceMarketplace\ServiceRequestCrudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceRequestOperationController extends Controller
{
    public function assign(
        Request $request,
        string $serviceRequest,
        ServiceRequestCrudService $service
    ): JsonResponse {
        /*
         * Resolve explicitly by primary key instead of relying on implicit
         * route-model binding. This keeps the HTTP contract stable while
         * avoiding route-binding drift in additive/RC route registration.
         */
        $serviceRequestModel = ServiceRequest::query()
            ->findOrFail(
                (int) $serviceRequest
            );

        $this->authorize(
            'update',
            $serviceRequestModel
        );

        $data = $request->validate([
            'assigned_to' => [
                'required',
                'integer',
                'exists:users,id',
            ],
        ]);

        $provider = User::query()->findOrFail(
            $data['assigned_to']
        );

        return response()->json([
            'data' => $service->assign(
                $serviceRequestModel,
                $provider
            ),
        ]);
    }
}
