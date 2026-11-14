<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChargePeriodRequest;
use App\Http\Requests\UpdateChargePeriodRequest;
use App\Http\Resources\V1\ChargePeriodResource;
use App\Models\Building;
use App\Models\ChargePeriod;
use App\Services\ChargePeriodScheduleService;
use App\Services\ChargePeriodService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChargePeriodController extends Controller
{
    public function index(
        Request $request,
        Building $building,
        PermissionChecker $permissions
    ): AnonymousResourceCollection {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'charge-periods.view',
                $building
            ),
            403
        );

        return ChargePeriodResource::collection(
            $building->chargePeriods()
                ->withCount(['chargeCalculations', 'chargeExpenseAllocations', 'unitInvoices'])
                ->latest('period_start')
                ->paginate(
                    min(
                        max($request->integer('per_page', 20), 1),
                        100
                    )
                )
                ->withQueryString()
        );
    }

    public function store(
        StoreChargePeriodRequest $request,
        Building $building,
        PermissionChecker $permissions
    ) {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'charge-periods.create',
                $building
            ),
            403
        );

        $period = app(ChargePeriodScheduleService::class)->save(
            $building, $request->validated(), $request->user()
        );

        return (new ChargePeriodResource($period))
            ->response()
            ->setStatusCode(201);
    }

    public function previewBatch(
        \App\Http\Requests\StoreChargePeriodBatchRequest $request,
        Building $building,
        PermissionChecker $permissions,
        \App\Services\ChargePeriodBatchService $service
    ): \Illuminate\Http\JsonResponse {
        abort_unless($permissions->allows($request->user(), 'charge-periods.create', $building), 403);
        return response()->json(['data' => $service->preview($building, $request->validated())]);
    }

    public function storeBatch(
        \App\Http\Requests\StoreChargePeriodBatchRequest $request,
        Building $building,
        PermissionChecker $permissions,
        \App\Services\ChargePeriodBatchService $service
    ) {
        abort_unless($permissions->allows($request->user(), 'charge-periods.create', $building), 403);
        return ChargePeriodResource::collection($service->create($building, $request->validated(), $request->user()))
            ->response()->setStatusCode(201);
    }

    public function show(
        ChargePeriod $chargePeriod
    ): ChargePeriodResource {
        $this->authorize('view', $chargePeriod);

        $chargePeriod->loadCount([
            'chargeCalculations',
            'chargeExpenseAllocations',
            'unitInvoices',
        ]);

        return new ChargePeriodResource($chargePeriod);
    }

    public function update(
        UpdateChargePeriodRequest $request,
        ChargePeriod $chargePeriod
    ): ChargePeriodResource {
        $this->authorize('update', $chargePeriod);

        $chargePeriod = app(ChargePeriodScheduleService::class)->save(
            $chargePeriod->building, $request->validated(), $request->user(), $chargePeriod
        );

        return new ChargePeriodResource($chargePeriod->refresh());
    }

    public function preview(Request $request, ChargePeriod $chargePeriod, ChargePeriodService $service, PermissionChecker $permissions): \Illuminate\Http\JsonResponse
    {
        abort_unless($permissions->allows($request->user(), 'charge-periods.calculate', $chargePeriod->building), 403);

        return response()->json(['data' => $service->preview($chargePeriod)]);
    }

    public function calculate(
        Request $request,
        ChargePeriod $chargePeriod,
        ChargePeriodService $service,
        PermissionChecker $permissions
    ): ChargePeriodResource {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'charge-periods.calculate',
                $chargePeriod->building
            ),
            403
        );

        $validated = $request->validate(['review_token' => ['required', 'string', 'size:64']]);
        $chargePeriod = $service->calculate($chargePeriod, $validated['review_token']);

        $chargePeriod->loadCount([
            'chargeCalculations',
            'chargeExpenseAllocations',
            'unitInvoices',
        ]);

        return new ChargePeriodResource($chargePeriod);
    }

    public function issue(
        Request $request,
        ChargePeriod $chargePeriod,
        ChargePeriodService $service,
        PermissionChecker $permissions
    ): ChargePeriodResource {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'charge-periods.issue',
                $chargePeriod->building
            ),
            403
        );

        $chargePeriod = $service->issue(
            $chargePeriod,
            $request->user()
        );

        $chargePeriod->loadCount([
            'chargeCalculations',
            'chargeExpenseAllocations',
            'unitInvoices',
        ]);

        return new ChargePeriodResource($chargePeriod);
    }
}
