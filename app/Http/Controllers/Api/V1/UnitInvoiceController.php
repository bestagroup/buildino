<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUnitInvoiceRequest;
use App\Http\Requests\UpdateUnitInvoiceRequest;
use App\Http\Resources\V1\UnitInvoiceResource;
use App\Models\Building;
use App\Models\Unit;
use App\Models\UnitInvoice;
use App\Services\InvoiceAccessService;
use App\Services\InvoiceService;
use App\Services\Security\UnitResidentAccessService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class UnitInvoiceController extends Controller
{
    public function buildingIndex(
        Request $request,
        Building $building,
        PermissionChecker $permissions
    ): AnonymousResourceCollection {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'invoices.view',
                $building
            ),
            403
        );

        $query = $building->unitInvoices()
            ->with([
                'unit:id,floor_id,unit_number,title',
                'payer:id,first_name,last_name',
            ]);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return UnitInvoiceResource::collection(
            $query->latest('id')
                ->paginate(
                    min(
                        max($request->integer('per_page', 20), 1),
                        100
                    )
                )
                ->withQueryString()
        );
    }

    public function unitIndex(
        Request $request,
        Unit $unit,
        PermissionChecker $permissions,
        UnitResidentAccessService $residentAccess
    ): AnonymousResourceCollection {
        $unit->loadMissing('floor.block.building');
        $building = $unit->floor?->block?->building;
        $managementAllowed = $building && $permissions->allows(
            $request->user(),
            'invoices.view',
            $building
        );
        $residentAllowed = $residentAccess->allows(
            $request->user(),
            $unit
        );

        abort_unless(
            $building && ($managementAllowed || $residentAllowed),
            403
        );

        $query = $unit->unitInvoices()
            ->with('payer:id,first_name,last_name');

        if (! $managementAllowed) {
            $query->where(function ($payer) use ($request): void {
                $payer
                    ->whereNull('payer_user_id')
                    ->orWhere(
                        'payer_user_id',
                        $request->user()->getKey()
                    );
            });
        }

        return UnitInvoiceResource::collection(
            $query
                ->latest('id')
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
        StoreUnitInvoiceRequest $request,
        Unit $unit,
        InvoiceService $service,
        PermissionChecker $permissions
    ) {
        $unit->loadMissing('floor.block.building');
        $building = $unit->floor?->block?->building;

        abort_unless(
            $building && $permissions->allows(
                $request->user(),
                'invoices.create',
                $building
            ),
            403
        );

        $invoice = $service->createManual(
            $unit,
            $request->user(),
            $request->validated()
        );

        $invoice->load([
            'unit:id,floor_id,unit_number,title',
            'invoiceItems',
            'invoiceInstallments',
            'payer:id,first_name,last_name',
        ]);

        return (new UnitInvoiceResource($invoice))
            ->response()
            ->setStatusCode(201);
    }

    public function show(
        Request $request,
        UnitInvoice $unitInvoice,
        InvoiceAccessService $access
    ): UnitInvoiceResource {
        abort_unless(
            $access->canView(
                $request->user(),
                $unitInvoice
            ),
            403
        );

        $unitInvoice->load([
            'unit:id,floor_id,unit_number,title',
            'invoiceItems',
            'invoiceInstallments',
            'payer:id,first_name,last_name',
        ]);

        return new UnitInvoiceResource($unitInvoice);
    }

    public function update(
        UpdateUnitInvoiceRequest $request,
        UnitInvoice $unitInvoice,
        InvoiceService $service
    ): UnitInvoiceResource {
        $this->authorize('update', $unitInvoice);

        $unitInvoice = $service->updateDraft(
            $unitInvoice,
            $request->validated()
        );

        $unitInvoice->load([
            'unit:id,floor_id,unit_number,title',
            'invoiceItems',
            'invoiceInstallments',
        ]);

        return new UnitInvoiceResource($unitInvoice);
    }

    public function destroy(
        UnitInvoice $unitInvoice,
        InvoiceService $service
    ): Response {
        $this->authorize('delete', $unitInvoice);

        $service->voidDraft($unitInvoice);

        return response()->noContent();
    }
}
