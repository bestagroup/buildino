<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BuildingBillType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteBuildingBillPaymentRequest;
use App\Http\Requests\FailBuildingBillPaymentRequest;
use App\Http\Requests\StoreBuildingBillPaymentRequest;
use App\Http\Resources\V1\BuildingBillPaymentResource;
use App\Models\Building;
use App\Models\BuildingBillPayment;
use App\Services\Wallet\BuildingBillPaymentService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BuildingBillPaymentController extends Controller
{
    public function index(
        Request $request,
        Building $building,
        PermissionChecker $permissions
    ): AnonymousResourceCollection {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'building-bills.view',
                $building
            ),
            403
        );

        return BuildingBillPaymentResource::collection(
            BuildingBillPayment::query()
                ->where('building_id', $building->getKey())
                ->latest('id')
                ->paginate(20)
        );
    }

    public function store(
        StoreBuildingBillPaymentRequest $request,
        Building $building,
        BuildingBillPaymentService $service,
        PermissionChecker $permissions
    ) {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'building-bills.create',
                $building
            ),
            403
        );

        $data = $request->validated();

        $bill = $service->request(
            $building,
            $request->user(),
            BuildingBillType::from($data['bill_type']),
            (int) $data['amount'],
            $data
        );

        return (new BuildingBillPaymentResource($bill))
            ->response()
            ->setStatusCode(201);
    }

    public function complete(
        CompleteBuildingBillPaymentRequest $request,
        BuildingBillPayment $buildingBillPayment,
        BuildingBillPaymentService $service,
        PermissionChecker $permissions
    ): BuildingBillPaymentResource {
        $buildingBillPayment->loadMissing('building');

        abort_unless(
            $permissions->allows(
                $request->user(),
                'building-bills.complete',
                $buildingBillPayment->building
            ),
            403
        );

        return new BuildingBillPaymentResource(
            $service->complete(
                $buildingBillPayment,
                $request->user(),
                $request->validated('provider_reference'),
                $request->validated('provider_payload')
            )
        );
    }

    public function fail(
        FailBuildingBillPaymentRequest $request,
        BuildingBillPayment $buildingBillPayment,
        BuildingBillPaymentService $service,
        PermissionChecker $permissions
    ): BuildingBillPaymentResource {
        $buildingBillPayment->loadMissing('building');

        abort_unless(
            $permissions->allows(
                $request->user(),
                'building-bills.fail',
                $buildingBillPayment->building
            ),
            403
        );

        return new BuildingBillPaymentResource(
            $service->fail(
                $buildingBillPayment,
                $request->user(),
                $request->validated('reason')
            )
        );
    }
}
