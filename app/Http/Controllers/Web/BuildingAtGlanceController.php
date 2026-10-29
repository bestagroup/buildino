<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Services\Web\BuildingAtGlanceService;
use App\Services\Security\BuildingPermissionScopeService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class BuildingAtGlanceController extends Controller
{
    public function __invoke(Request $request, BuildingPermissionScopeService $scope, PermissionChecker $permissions, BuildingAtGlanceService $reports): View
    {
        $data = $request->validate(['building_id' => ['nullable', 'integer', 'min:1']]);
        $buildingIds = $scope->buildingIds($request->user(), 'reports.financial.view');
        $buildings = Building::query()->when($buildingIds !== null, fn ($query) => $query->whereIn('id', $buildingIds))->orderBy('title')->orderBy('id')->get()->filter(function (Building $building) use ($request, $permissions): bool {
            foreach (['reports.financial.view', 'units.view', 'unit-ownerships.view', 'unit-occupancies.view'] as $permission) {
                if (! $permissions->allows($request->user(), $permission, $building)) {
                    return false;
                }
            }
            return true;
        })->values();
        $building = isset($data['building_id']) ? $buildings->firstWhere('id', (int) $data['building_id']) : $buildings->first();
        abort_if(isset($data['building_id']) && ! $building, 403);
        return view('management.at-a-glance', [
            'user' => $request->user(), 'buildings' => $buildings, 'building' => $building,
            'report' => $building ? $reports->report($building) : null,
            'canViewInvoices' => $building && $permissions->allows($request->user(), 'invoices.view', $building),
            'canViewPeriods' => $building && $permissions->allows($request->user(), 'charge-periods.view', $building),
        ]);
    }
}
