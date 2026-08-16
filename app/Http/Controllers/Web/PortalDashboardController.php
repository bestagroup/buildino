<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Web\PortalAccessService;
use App\Services\Web\PortalDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PortalDashboardController extends Controller
{
    public function index(
        Request $request,
        PortalAccessService $access
    ): RedirectResponse {
        $area =
            $access->defaultArea(
                $request->user()
            );

        return redirect()
            ->route(
                "portal.{$area}.dashboard"
            );
    }

    public function resident(
        Request $request,
        PortalAccessService $access,
        PortalDashboardService $dashboard
    ): View {
        return view(
            'portal.resident.dashboard',
            [
                'portalData' =>
                    $dashboard->resident(
                        $request->user()
                    ),

                'portalAreas' => [
                    'resident' =>
                        true,

                    'provider' =>
                        $access
                            ->hasProviderAccess(
                                $request->user()
                            ),
                ],
            ]
        );
    }

    public function provider(
        Request $request,
        PortalAccessService $access,
        PortalDashboardService $dashboard
    ): View {
        return view(
            'portal.provider.dashboard',
            [
                'portalData' =>
                    $dashboard->provider(
                        $request->user()
                    ),

                'portalAreas' => [
                    'resident' =>
                        $access
                            ->hasResidentAccess(
                                $request->user()
                            ),

                    'provider' =>
                        true,
                ],
            ]
        );
    }
}
