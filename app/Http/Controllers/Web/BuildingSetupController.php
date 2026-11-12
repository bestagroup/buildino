<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Complex;
use App\Models\Floor;
use App\Models\Unit;
use App\Models\BuildingComplexRequest;
use App\Services\System\IranLocations;
use App\Services\Web\ManagementDashboardAccessService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class BuildingSetupController extends Controller
{
    public function __invoke(Request $request, ManagementDashboardAccessService $access, PermissionChecker $permissions, IranLocations $locations): View
    {
        $user = $request->user();
        abort_unless($permissions->allowsAnyScope($user, 'buildings.create') || $permissions->allowsAnyScope($user, 'blocks.create'), 403);

        $available = $access->accessibleBuildings($user)
            ->filter(fn ($building) => $permissions->allows($user, 'blocks.view', $building)
                && $permissions->allows($user, 'floors.view', $building)
                && $permissions->allows($user, 'units.view', $building)
                && $permissions->allows($user, 'blocks.create', $building)
                && $permissions->allows($user, 'floors.create', $building)
                && $permissions->allows($user, 'units.create', $building));
        $incomplete = app(\App\Services\Web\BuildingSetupProgressService::class)->incomplete($user, $available);
        $buildings = $available
            ->map(fn ($building) => [
                'id' => $building->id,
                'title' => $building->title,
                'complexTitle' => $building->complex?->title,
                'address' => $building->address,
                'structureType' => $building->structure_type,
                'complexId' => $building->complex_id,
                'complexType' => $building->complex?->workspace_type,
                'canUpdate' => $permissions->allows($user, 'buildings.update', $building),
                'canSetupOwners' => $permissions->allows($user, 'users.create', $building) && $permissions->allows($user, 'unit-ownerships.create', $building),
                'canSetupResidents' => $permissions->allows($user, 'users.create', $building) && $permissions->allows($user, 'unit-occupancies.create', $building),
                'postalCode' => $building->postal_code,
                'parkingCount' => $building->parking_count,
                'storageCount' => $building->storage_count,
            ])->values();

        $complexes = $permissions->allowsAnyScope($user, 'buildings.create')
            ? Complex::query()->where('is_active', true)->orderBy('title')->get()
                ->filter(fn ($complex) => $permissions->allows($user, 'buildings.create', $complex)
                    && !in_array($complex->workspace_type, ['standalone', 'unlinked'], true))
                ->map(fn ($complex) => ['id' => $complex->id, 'title' => $complex->title, 'code' => $complex->code, 'workspaceType' => $complex->workspace_type])->values()
            : collect();

        $canCreateBuilding = $permissions->allowsAnyScope($user, 'buildings.create')
            && ($permissions->allows($user, 'complexes.create') || $complexes->isNotEmpty());
        $startNew = $canCreateBuilding && $request->boolean('new');
        $initialBuildingId = $request->integer('building_id') ?: ($startNew || $request->filled('complex_id')
            ? 0 : ($incomplete->first()?->id ?? ($available->count() === 1 ? $available->first()->id : 0)));
        $selected = $available->firstWhere('id', $initialBuildingId);
        $completedSetup = null;
        if ($selected?->setup_completed_at && ! $incomplete->contains('id', $selected->id)) {
            $units = Unit::query()->whereHas('floor.block', fn ($query) => $query->where('building_id', $selected->id));
            $completedSetup = [
                'buildingId' => $selected->id, 'complexId' => $selected->complex_id,
                'floors' => Floor::query()->whereHas('block', fn ($query) => $query->where('building_id', $selected->id))->count(),
                'units' => (clone $units)->count(), 'firstUnitId' => $units->orderBy('floor_id')->orderBy('unit_number')->value('id'),
                'canManageOwnership' => $permissions->allows($user, 'unit-ownerships.create', $selected),
                'canInvite' => $permissions->allows($user, 'unit-invitations.create', $selected),
            ];
        }

        return view('management.building-setup', [
            'connections' => BuildingComplexRequest::query()->with('building:id,title,complex_id')
                ->whereIn('building_id', $available->pluck('id'))->latest()->get(),
            'connectionReviews' => BuildingComplexRequest::query()->with('building:id,title,address,complex_id', 'complex:id,title')
                ->whereIn('complex_id', $complexes->pluck('id'))->where('status', 'pending')->get()
                ->filter(fn ($connection) => $permissions->allows($user, 'complexes.update', $connection->complex)),
            'setupConfig' => [
                'actorId' => $user->id,
                'apiBase' => url('/api/v1'),
                'csrfToken' => csrf_token(),
                'buildings' => $buildings,
                'complexes' => $complexes,
                'canCreateComplex' => $permissions->allows($user, 'complexes.create'),
                'canCreateBuilding' => $canCreateBuilding,
                'startNew' => $startNew,
                'completedSetup' => $completedSetup,
                'canInvite' => $permissions->allowsAnyScope($user, 'unit-invitations.create'),
                'canManageOwnership' => $permissions->allowsAnyScope($user, 'unit-ownerships.create'),
                'canSetupOwners' => $permissions->allowsAnyScope($user, 'users.create') && $permissions->allowsAnyScope($user, 'unit-ownerships.create'),
                'canSetupResidents' => $permissions->allowsAnyScope($user, 'users.create') && $permissions->allowsAnyScope($user, 'unit-occupancies.create'),
                'locations' => $locations->forForm(),
                'initialBuildingId' => $initialBuildingId,
                'initialComplexId' => $request->integer('complex_id'),
                'completeUrl' => url('/management/building-setup'),
                'dashboardUrl' => route('management.dashboard'),
                'unitsUrl' => route('management.operations.show', 'units'),
                'invitationsUrl' => route('management.operations.show', 'invitations'),
                'ownershipUrl' => route('management.operations.show', 'ownerships'),
            ],
        ]);
    }
}
