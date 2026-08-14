<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\WalletReconciliationResource;
use App\Models\Wallet;
use App\Services\Wallet\WalletReconciliationService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WalletReconciliationController extends Controller
{
    public function index(
        Request $request,
        Wallet $wallet,
        PermissionChecker $permissions
    ): AnonymousResourceCollection {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'wallet-reconciliation.view',
                null
            ),
            403
        );

        return WalletReconciliationResource::collection(
            $wallet->reconciliations()
                ->latest('id')
                ->paginate(20)
        );
    }

    public function run(
        Request $request,
        Wallet $wallet,
        WalletReconciliationService $service,
        PermissionChecker $permissions
    ): WalletReconciliationResource {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'wallet-reconciliation.run',
                null
            ),
            403
        );

        return new WalletReconciliationResource(
            $service->reconcile(
                $wallet,
                $request->user()
            )
        );
    }
}
