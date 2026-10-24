<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Unit;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class FacilityReservationPageController extends Controller
{
    public function __invoke(Request $request, PermissionChecker $permissions): View
    {
        $user = $request->user();
        $buildings = Building::query()->orderBy('title')->get()
            ->filter(fn (Building $building): bool => $permissions->allows($user, 'facility-reservations.view', $building))
            ->map(fn (Building $building): array => [
                'id' => $building->id, 'title' => $building->title, 'currency' => $building->currency, 'timezone' => $building->timezone,
                'canCreate' => $permissions->allows($user, 'facility-reservations.create', $building),
                'canCancel' => $permissions->allows($user, 'facility-reservations.cancel', $building),
                'canApprove' => $permissions->allows($user, 'facility-reservations.approve', $building),
            ])->values();

        $units = Unit::query()->with('floor.block')
            ->whereHas('floor.block', fn ($query) => $query->whereIn('building_id', $buildings->where('canCreate', true)->pluck('id')))
            ->orderBy('floor_id')->orderBy('unit_number')->get()
            ->map(fn (Unit $unit): array => [
                'id' => $unit->id, 'buildingId' => $unit->floor->block->building_id,
                'label' => $unit->floor->block->title.' / '.($unit->floor->title ?: 'طبقه '.$unit->floor->floor_number).' / واحد '.$unit->unit_number,
            ]);

        return view('management.facility-reservations', ['reservationConfig' => [
            'buildings' => $buildings, 'units' => $units, 'actorId' => $user->id,
        ]]);
    }
}
