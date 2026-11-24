<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Web\ManagementHeaderContextService;
use App\Services\Web\PortalAccessService;
use App\Services\Web\PortalDashboardService;
use App\Services\Web\PortalOperationDetailService;
use App\Services\Web\ResidentPortalWorkspace;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class PortalOperationsController extends Controller
{
    public function residentIndex(
        Request $request,
        string $resource,
        PortalAccessService $access,
        ManagementHeaderContextService $header
    ): View {
        if ($resource === 'invoices') {
            return app(UnitStatementController::class)->index($request);
        }

        $config =
            config(
                "portal_operations.resident.{$resource}"
            );

        abort_unless(
            is_array(
                $config
            ),
            Response::HTTP_NOT_FOUND
        );

        $workspace = app(ResidentPortalWorkspace::class);
        $data = in_array($resource, ['units', 'loyalty'], true)
            ? app(PortalDashboardService::class)->resident($request->user())
            : $workspace->context($request->user());
        if ($request->filled('unit_id')) {
            $request->validate(['unit_id' => ['integer', 'min:1']]);
            abort_unless(collect($data['units'])->contains('id', $request->integer('unit_id')), 403);
        }

        return view(
            in_array($resource, ['units', 'loyalty'], true) ? 'portal.resident.'.$resource : 'portal.operations.index',
            [
                'portalData' => [...$data,
                    'area' => 'resident',
                    'header' => $header->context(
                        $request->user()
                    ),
                ],
                'portalAreas' => [
                    'resident' => true,
                    'provider' => $access->hasProviderAccess(
                        $request->user()
                    ),
                ],
                'area' => 'resident',
                'resource' => $resource,
                'residentStatuses' => $workspace->statuses($resource),
                'operationConfig' => $config,
                'dataUrl' => route(
                    'portal.resident.datatables',
                    [
                        'table' => $resource,
                    ]
                ),
            ]
        );
    }

    public function residentShow(
        Request $request,
        string $resource,
        int $id,
        PortalAccessService $access,
        ManagementHeaderContextService $header,
        PortalOperationDetailService $details
    ): View {
        $config =
            config(
                "portal_operations.resident.{$resource}"
            );

        abort_unless(
            is_array(
                $config
            ),
            Response::HTTP_NOT_FOUND
        );

        $detail = $details->resident($request->user(), $resource, $id);
        $workspace = app(ResidentPortalWorkspace::class);

        return view(
            'portal.operations.show',
            [
                'portalData' => [...$workspace->context($request->user()),
                    'area' => 'resident',
                    'header' => $header->context(
                        $request->user()
                    ),
                ],
                'portalAreas' => [
                    'resident' => true,
                    'provider' => $access->hasProviderAccess(
                        $request->user()
                    ),
                ],
                'area' => 'resident',
                'resource' => $resource,
                'operationConfig' => $config,
                'detail' => $detail,
                'residentActions' => $workspace->actions($request->user(), $resource, $id),
            ]
        );
    }

    public function providerIndex(
        Request $request,
        string $resource,
        PortalAccessService $access,
        ManagementHeaderContextService $header
    ): View {
        $config =
            config(
                "portal_operations.provider.{$resource}"
            );

        abort_unless(
            is_array(
                $config
            ),
            Response::HTTP_NOT_FOUND
        );

        return view(
            'portal.operations.index',
            [
                'portalData' => [
                    'area' =>
                        'provider',
                    'header' =>
                        $header->context(
                            $request->user()
                        ),
                ],
                'portalAreas' => [
                    'resident' =>
                        $access->hasResidentAccess(
                            $request->user()
                        ),
                    'provider' =>
                        true,
                ],
                'area' =>
                    'provider',
                'resource' =>
                    $resource,
                'operationConfig' =>
                    $config,
                'dataUrl' =>
                    route(
                        'portal.provider.datatables',
                        [
                            'table' =>
                                $resource,
                        ]
                    ),
            ]
        );
    }

    public function providerShow(
        Request $request,
        string $resource,
        int $id,
        PortalAccessService $access,
        ManagementHeaderContextService $header,
        PortalOperationDetailService $details
    ): View {
        $config =
            config(
                "portal_operations.provider.{$resource}"
            );

        abort_unless(
            is_array(
                $config
            ),
            Response::HTTP_NOT_FOUND
        );

        return view(
            'portal.operations.show',
            [
                'portalData' => [
                    'area' =>
                        'provider',
                    'header' =>
                        $header->context(
                            $request->user()
                        ),
                ],
                'portalAreas' => [
                    'resident' =>
                        $access->hasResidentAccess(
                            $request->user()
                        ),
                    'provider' =>
                        true,
                ],
                'area' =>
                    'provider',
                'resource' =>
                    $resource,
                'operationConfig' =>
                    $config,
                'detail' =>
                    $details->provider(
                        $request->user(),
                        $resource,
                        $id
                    ),
            ]
        );
    }
}
