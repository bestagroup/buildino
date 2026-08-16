<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Mobile\MobileBootstrapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileBootstrapController extends Controller
{
    public function __invoke(
        Request $request,
        MobileBootstrapService $bootstrap
    ): JsonResponse {
        return response()->json([
            'data' =>
                $bootstrap->build(
                    $request->user(),
                    $request
                ),
        ]);
    }
}
