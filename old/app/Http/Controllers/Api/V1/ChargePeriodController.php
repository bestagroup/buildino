<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ChargePeriodStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChargePeriodRequest;
use App\Http\Requests\UpdateChargePeriodRequest;
use App\Http\Resources\V1\ChargePeriodResource;
use App\Models\Building;
use App\Models\ChargePeriod;
use App\Services\ChargePeriodService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

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
                ->withCount(['chargeCalculations','unitInvoices'])
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

        $data = $request->validated();

        $overlap = ChargePeriod::query()
            ->where('building_id', $building->id)
            ->whereNotIn('status', [
                ChargePeriodStatus::Cancelled->value,
            ])
            ->whereDate('period_start', '<=', $data['period_end'])
            ->whereDate('period_end', '>=', $data['period_start'])
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'period_start' => 'This charge period overlaps an existing period.',
            ]);
        }

        $period = $building->chargePeriods()->create([
            ...$data,
            'status' => ChargePeriodStatus::Draft,
            'created_by' => $request->user()->getKey(),
        ]);

        return (new ChargePeriodResource($period))
            ->response()
            ->setStatusCode(201);
    }

    public function show(
        ChargePeriod $chargePeriod
    ): ChargePeriodResource {
        $this->authorize('view', $chargePeriod);

        $chargePeriod->loadCount([
            'chargeCalculations',
            'unitInvoices',
        ]);

        return new ChargePeriodResource($chargePeriod);
    }

    public function update(
        UpdateChargePeriodRequest $request,
        ChargePeriod $chargePeriod
    ): ChargePeriodResource {
        $this->authorize('update', $chargePeriod);

        if ($chargePeriod->status !== ChargePeriodStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only a draft charge period can be edited.',
            ]);
        }

        $chargePeriod->update($request->validated());

        return new ChargePeriodResource($chargePeriod->refresh());
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

        $chargePeriod = $service->calculate($chargePeriod);

        $chargePeriod->loadCount([
            'chargeCalculations',
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
            'unitInvoices',
        ]);

        return new ChargePeriodResource($chargePeriod);
    }
}
