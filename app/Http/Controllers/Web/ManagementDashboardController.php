<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ManagementDashboardRequest;
use App\Models\Building;
use App\Services\Web\ManagementDashboardAccessService;
use App\Services\Web\ManagementDashboardService;
use App\Services\Web\ManagementRoleDashboardService;
use App\Services\Web\ManagementUiContextService;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ManagementDashboardController extends Controller
{
    public function index(
        ManagementDashboardRequest $request,
        ManagementDashboardAccessService $access,
        ManagementDashboardService $dashboard,
        ManagementUiContextService $ui,
        ManagementRoleDashboardService $roleDashboard
    ): View {
        $user = $request->user();

        $buildings =
            $access->accessibleBuildings(
                $user
            );

        $platformAccess =
            $access->hasPlatformAccess(
                $user
            );

        $selectedBuilding = null;

        if ($request->filled('building_id')) {
            $selectedBuilding =
                $buildings->first(
                    fn (Building $building): bool =>
                        $building->getKey()
                        === $request->integer(
                            'building_id'
                        )
                );

            abort_unless(
                $selectedBuilding !== null,
                Response::HTTP_FORBIDDEN
            );
        } elseif (
            ! $platformAccess
            && $buildings->count() === 1
        ) {
            $selectedBuilding =
                $buildings->first();
        }

        $data = $dashboard->build(
            $user,
            $buildings,
            $selectedBuilding,
            $request->validated('from'),
            $request->validated('to')
        );

        $uiContext =
            $ui->context(
                $user
            );

        $roleDashboardData =
            $roleDashboard->build(
                $user,
                $data,
                $uiContext
            );

        return view(
            'management.dashboard',
            [
                'user' =>
                    $user,
                'buildings' =>
                    $buildings,
                'selectedBuilding' =>
                    $selectedBuilding,
                'dashboard' =>
                    $data,
                'platformAccess' =>
                    $platformAccess,
                'roleDashboard' =>
                    $roleDashboardData,
            ]
        );
    }
}
