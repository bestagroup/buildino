<?php

namespace App\Services\Security;

use App\Models\Building;
use App\Models\BuildingFacility;
use App\Models\FacilityReservation;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\SupportTicket;
use App\Models\Unit;
use App\Models\UnitInvoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class BuildingContextResolver
{
    public function resolve(Request $request): ?Building
    {
        $building = $this->routeModel($request, 'building');

        if ($building instanceof Building) {
            return $building;
        }

        if (is_numeric($building)) {
            return Building::query()->find((int) $building);
        }

        if ($request->filled('building_id')) {
            return Building::query()->find((int) $request->input('building_id'));
        }

        $unit = $this->routeModel($request, 'unit');

        if ($unit instanceof Unit) {
            return $this->buildingFromUnit($unit);
        }

        if (is_numeric($unit)) {
            $unit = Unit::query()->with('floor.block.building')->find((int) $unit);
            return $unit ? $this->buildingFromUnit($unit) : null;
        }

        $facility = $this->routeModel($request, 'buildingFacility')
            ?? $this->routeModel($request, 'facility');

        if ($facility instanceof BuildingFacility) {
            return $facility->building;
        }

        $reservation = $this->routeModel($request, 'facilityReservation');

        if ($reservation instanceof FacilityReservation) {
            return $reservation->buildingFacility?->building;
        }

        $invoice = $this->routeModel($request, 'unitInvoice')
            ?? $this->routeModel($request, 'invoice');

        if ($invoice instanceof UnitInvoice) {
            return $invoice->building;
        }

        $payment = $this->routeModel($request, 'payment');

        if ($payment instanceof Payment) {
            return $payment->building;
        }

        $ticket = $this->routeModel($request, 'supportTicket');

        if ($ticket instanceof SupportTicket) {
            return $ticket->building;
        }

        $serviceRequest = $this->routeModel($request, 'serviceRequest');

        if ($serviceRequest instanceof ServiceRequest) {
            return $serviceRequest->building;
        }

        return null;
    }

    private function routeModel(Request $request, string $key): Model|int|string|null
    {
        return $request->route($key);
    }

    private function buildingFromUnit(Unit $unit): ?Building
    {
        return $unit->floor?->block?->building;
    }
}
