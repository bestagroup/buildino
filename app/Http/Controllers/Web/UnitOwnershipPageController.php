<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Unit;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class UnitOwnershipPageController extends Controller
{
    public function __invoke(Request $request, PermissionChecker $permissions): View
    {
        $user = $request->user();

        abort_unless($permissions->allowsAnyScope($user, 'unit-ownerships.view'), 403);

        $buildings = Building::query()
            ->with('complex:id,title')
            ->orderBy('title')
            ->get()
            ->filter(fn (Building $building): bool => $permissions->allows($user, 'unit-ownerships.view', $building))
            ->map(fn (Building $building): array => [
                'id' => $building->id,
                'title' => $building->title,
                'complexTitle' => $building->complex?->title,
                'canCreate' => $permissions->allows($user, 'unit-ownerships.create', $building),
                'canUpdate' => $permissions->allows($user, 'unit-ownerships.update', $building),
                'canCreateUser' => $permissions->allows($user, 'users.create', $building),
            ])
            ->values();

        $initialUnit = null;
        if ($request->integer('unit_id') > 0) {
            $unit = Unit::query()->with('floor.block.building')->find($request->integer('unit_id'));
            $floor = $unit?->floor;
            $block = $floor?->block;

            if ($block && $buildings->contains('id', $block->building_id)) {
                $initialUnit = [
                    'buildingId' => $block->building_id,
                    'blockId' => $block->id,
                    'floorId' => $floor->id,
                    'unitId' => $unit->id,
                ];
            }
        }

        return view('management.unit-ownerships', [
            'ownershipConfig' => [
                'apiBase' => '/api/v1',
                'lookupBase' => '/management/lookups',
                'csrfToken' => csrf_token(),
                'buildings' => $buildings,
                'initialUnit' => $initialUnit,
                'usersUrl' => route('management.operations.show', 'users'),
                'buildingSetupUrl' => route('management.building-setup'),
            ],
        ]);
    }
}
