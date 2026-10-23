<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Complex;
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

        $buildings = $access->accessibleBuildings($user)
            ->filter(fn ($building) => $permissions->allows($user, 'blocks.view', $building)
                && $permissions->allows($user, 'floors.view', $building)
                && $permissions->allows($user, 'units.view', $building)
                && $permissions->allows($user, 'blocks.create', $building))
            ->map(fn ($building) => [
                'id' => $building->id,
                'title' => $building->title,
                'complexTitle' => $building->complex?->title,
                'address' => $building->address,
            ])->values();

        $complexes = $permissions->allowsAnyScope($user, 'buildings.create')
            ? Complex::query()->where('is_active', true)->orderBy('title')->get()
                ->filter(fn ($complex) => $permissions->allows($user, 'buildings.create', $complex))
                ->map(fn ($complex) => ['id' => $complex->id, 'title' => $complex->title])->values()
            : collect();

        return view('management.building-setup', [
            'setupConfig' => [
                'actorId' => $user->id,
                'apiBase' => url('/api/v1'),
                'csrfToken' => csrf_token(),
                'buildings' => $buildings,
                'complexes' => $complexes,
                'canCreateComplex' => $permissions->allows($user, 'complexes.create'),
                'canCreateBuilding' => $permissions->allowsAnyScope($user, 'buildings.create')
                    && ($permissions->allows($user, 'complexes.create') || $complexes->isNotEmpty()),
                'canInvite' => $permissions->allowsAnyScope($user, 'unit-invitations.create'),
                'canManageOwnership' => $permissions->allowsAnyScope($user, 'unit-ownerships.create'),
                'locations' => $locations->forForm(),
                'initialBuildingId' => $request->integer('building_id'),
                'dashboardUrl' => route('management.dashboard'),
                'unitsUrl' => route('management.operations.show', 'units'),
                'invitationsUrl' => route('management.operations.show', 'invitations'),
                'ownershipUrl' => route('management.operations.show', 'ownerships'),
            ],
        ]);
    }
}
