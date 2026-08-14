<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\WalletTopUpTargetType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWalletTopUpRequest;
use App\Http\Resources\V1\WalletTopUpResource;
use App\Models\Building;
use App\Models\Unit;
use App\Models\WalletTopUp;
use App\Services\Security\BuildingAccessService;
use App\Services\Security\UnitResidentAccessService;
use App\Services\Wallet\WalletTopUpService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;

class WalletTopUpController extends Controller
{
    public function store(
        StoreWalletTopUpRequest $request,
        Building $building,
        WalletTopUpService $service,
        BuildingAccessService $buildingAccess,
        UnitResidentAccessService $residentAccess,
        PermissionChecker $permissions
    ) {
        $user = $request->user();

        abort_unless(
            $buildingAccess->allows(
                $user,
                $building
            ),
            403
        );

        $type = WalletTopUpTargetType::from(
            $request->validated('target_type')
        );

        if (
            $type
            === WalletTopUpTargetType::UserWallet
        ) {
            $target = $user;
        } else {
            $target = Unit::query()
                ->with('floor.block.building')
                ->findOrFail(
                    $request->integer('unit_id')
                );

            if (
                (int) $target->floor?->block?->building_id
                !== (int) $building->getKey()
            ) {
                abort(404);
            }

            $allowed = $residentAccess->allows(
                $user,
                $target
            ) || $permissions->allows(
                $user,
                'wallets.topup',
                $building
            );

            abort_unless(
                $allowed,
                403
            );
        }

        $topUp = $service->create(
            $building,
            $user,
            $target,
            $request->validated()
        );

        $topUp->load([
            'payment',
            'wallet',
        ]);

        return (new WalletTopUpResource($topUp))
            ->response()
            ->setStatusCode(201);
    }

    public function show(
        Request $request,
        WalletTopUp $walletTopUp,
        PermissionChecker $permissions
    ): WalletTopUpResource {
        $walletTopUp->loadMissing([
            'payment.building',
            'wallet',
            'target',
        ]);

        $payment = $walletTopUp->payment;

        $allowed = $payment
            && (int) $payment->payer_user_id
                === (int) $request->user()->getKey();

        if (
            ! $allowed
            && $payment?->building
        ) {
            $allowed = $permissions->allows(
                $request->user(),
                'wallets.view',
                $payment->building
            );
        }

        abort_unless(
            $allowed,
            403
        );

        return new WalletTopUpResource(
            $walletTopUp
        );
    }
}
