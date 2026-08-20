<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MarkWalletPayoutPaidRequest;
use App\Http\Requests\RejectWalletPayoutRequest;
use App\Http\Requests\StoreWalletPayoutRequest;
use App\Http\Resources\V1\WalletPayoutResource;
use App\Models\Building;
use App\Models\BuildingBankAccount;
use App\Models\WalletPayoutRequest;
use App\Services\Wallet\WalletPayoutService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WalletPayoutController extends Controller
{
    public function index(
        Request $request,
        Building $building,
        PermissionChecker $permissions
    ): AnonymousResourceCollection {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'wallet-payouts.view',
                $building
            ),
            403
        );

        return WalletPayoutResource::collection(
            WalletPayoutRequest::query()
                ->where('building_id', $building->getKey())
                ->latest('id')
                ->paginate(20)
        );
    }

    public function store(
        StoreWalletPayoutRequest $request,
        Building $building,
        WalletPayoutService $service,
        PermissionChecker $permissions
    ) {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'wallet-payouts.create',
                $building
            ),
            403
        );

        $account = BuildingBankAccount::query()
            ->findOrFail(
                $request->integer('building_bank_account_id')
            );

        $payout = $service->request(
            $building,
            $account,
            $request->user(),
            $request->integer('amount'),
            $request->validated('idempotency_key')
        );

        return (new WalletPayoutResource($payout))
            ->response()
            ->setStatusCode(201);
    }

    public function approve(
        Request $request,
        WalletPayoutRequest $walletPayoutRequest,
        WalletPayoutService $service,
        PermissionChecker $permissions
    ): WalletPayoutResource {
        $walletPayoutRequest->loadMissing('building');

        abort_unless(
            $permissions->allows(
                $request->user(),
                'wallet-payouts.approve',
                $walletPayoutRequest->building
            ),
            403
        );

        return new WalletPayoutResource(
            $service->approve(
                $walletPayoutRequest,
                $request->user()
            )
        );
    }

    public function reject(
        RejectWalletPayoutRequest $request,
        WalletPayoutRequest $walletPayoutRequest,
        WalletPayoutService $service,
        PermissionChecker $permissions
    ): WalletPayoutResource {
        $walletPayoutRequest->loadMissing('building');

        abort_unless(
            $permissions->allows(
                $request->user(),
                'wallet-payouts.reject',
                $walletPayoutRequest->building
            ),
            403
        );

        return new WalletPayoutResource(
            $service->reject(
                $walletPayoutRequest,
                $request->user(),
                $request->validated('reason')
            )
        );
    }

    public function markPaid(
        MarkWalletPayoutPaidRequest $request,
        WalletPayoutRequest $walletPayoutRequest,
        WalletPayoutService $service,
        PermissionChecker $permissions
    ): WalletPayoutResource {
        $walletPayoutRequest->loadMissing('building');

        abort_unless(
            $permissions->allows(
                $request->user(),
                'wallet-payouts.pay',
                $walletPayoutRequest->building
            ),
            403
        );

        return new WalletPayoutResource(
            $service->markPaid(
                $walletPayoutRequest,
                $request->user(),
                $request->validated('bank_reference')
            )
        );
    }
}
