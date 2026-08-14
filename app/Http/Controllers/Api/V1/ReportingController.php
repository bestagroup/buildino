<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReceivablesReportRequest;
use App\Http\Requests\ReportDateRangeRequest;
use App\Models\Building;
use App\Services\Reports\BuildingReportService;
use App\Services\Reports\PlatformReportService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\JsonResponse;

class ReportingController extends Controller
{

    public function managementDashboard(
        ReportDateRangeRequest $request,
        Building $building,
        BuildingReportService $reports,
        PermissionChecker $permissions
    ): JsonResponse {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'reports.dashboard.view',
                $building
            ),
            403
        );

        return response()->json([
            'data' =>
                $reports->managementDashboard(
                    $building,
                    $request->validated('from'),
                    $request->validated('to')
                ),
        ]);
    }

    public function financialSummary(
        ReportDateRangeRequest $request,
        Building $building,
        BuildingReportService $reports,
        PermissionChecker $permissions
    ): JsonResponse {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'reports.financial.view',
                $building
            ),
            403
        );

        return response()->json([
            'data' =>
                $reports->financialSummary(
                    $building,
                    $request->validated('from'),
                    $request->validated('to')
                ),
        ]);
    }

    public function receivables(
        ReceivablesReportRequest $request,
        Building $building,
        BuildingReportService $reports,
        PermissionChecker $permissions
    ): JsonResponse {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'reports.receivables.view',
                $building
            ),
            403
        );

        return response()->json([
            'data' =>
                $reports->receivables(
                    $building,
                    $request->validated('as_of')
                ),
        ]);
    }

    public function cashFlow(
        ReportDateRangeRequest $request,
        Building $building,
        BuildingReportService $reports,
        PermissionChecker $permissions
    ): JsonResponse {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'reports.financial.view',
                $building
            ),
            403
        );

        return response()->json([
            'data' =>
                $reports->cashFlow(
                    $building,
                    $request->validated('from'),
                    $request->validated('to'),
                    $request->validated(
                        'granularity'
                    ) ?? 'day'
                ),
        ]);
    }

    public function facilities(
        ReportDateRangeRequest $request,
        Building $building,
        BuildingReportService $reports,
        PermissionChecker $permissions
    ): JsonResponse {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'reports.operations.view',
                $building
            ),
            403
        );

        return response()->json([
            'data' =>
                $reports->facilities(
                    $building,
                    $request->validated('from'),
                    $request->validated('to')
                ),
        ]);
    }

    public function services(
        ReportDateRangeRequest $request,
        Building $building,
        BuildingReportService $reports,
        PermissionChecker $permissions
    ): JsonResponse {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'reports.operations.view',
                $building
            ),
            403
        );

        return response()->json([
            'data' =>
                $reports->services(
                    $building,
                    $request->validated('from'),
                    $request->validated('to')
                ),
        ]);
    }

    public function platformSummary(
        ReportDateRangeRequest $request,
        PlatformReportService $reports,
        PermissionChecker $permissions
    ): JsonResponse {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'reports.platform.view',
                null
            ),
            403
        );

        return response()->json([
            'data' =>
                $reports->summary(
                    $request->validated('from'),
                    $request->validated('to'),
                    $request->validated(
                        'currency'
                    ) ?? 'IRR'
                ),
        ]);
    }
}
