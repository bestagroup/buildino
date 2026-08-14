<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\System\SystemHealthService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemHealthController extends Controller
{
    public function readiness(
        SystemHealthService $health
    ): JsonResponse {
        $result =
            $health->publicReadiness();

        return response()->json(
            [
                'data' => $result,
            ],
            $result['ready']
                ? 200
                : 503
        );
    }

    public function admin(
        Request $request,
        SystemHealthService $health,
        PermissionChecker $permissions
    ): JsonResponse {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'system.health.view',
                null
            ),
            403
        );

        $result = $health->inspect(
            true
        );

        return response()->json([
            'data' => $result,
        ]);
    }
}
