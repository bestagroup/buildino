<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\WalletEntryResource;
use App\Http\Resources\V1\WalletResource;
use App\Models\Building;
use App\Models\Unit;
use App\Models\Wallet;
use App\Services\Security\UnitResidentAccessService;
use App\Services\Wallet\WalletAccessService;
use App\Services\Wallet\WalletService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WalletController extends Controller
{
    public function me(
        Request $request,
        WalletService $wallets
    ): WalletResource {
        return new WalletResource(
            $wallets->walletFor(
                $request->user(),
                'IRR'
            )
        );
    }

    public function unit(
        Request $request,
        Unit $unit,
        WalletService $wallets,
        UnitResidentAccessService $residentAccess,
        PermissionChecker $permissions
    ): WalletResource {
        $unit->loadMissing(
            'floor.block.building'
        );

        $building = $unit->floor?->block?->building;

        abort_if(
            $building === null,
            404
        );

        $allowed = $residentAccess->allows(
            $request->user(),
            $unit
        ) || $permissions->allows(
            $request->user(),
            'wallets.view',
            $building
        );

        abort_unless(
            $allowed,
            403
        );

        return new WalletResource(
            $wallets->walletFor(
                $unit,
                strtoupper(
                    $building->currency ?: 'IRR'
                )
            )
        );
    }

    public function building(
        Request $request,
        Building $building,
        WalletService $wallets,
        PermissionChecker $permissions
    ): WalletResource {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'building-wallet.view',
                $building
            ),
            403
        );

        return new WalletResource(
            $wallets->walletFor(
                $building,
                strtoupper(
                    $building->currency ?: 'IRR'
                )
            )
        );
    }

    public function entries(
        Request $request,
        Wallet $wallet,
        WalletAccessService $access
    ): AnonymousResourceCollection {
        abort_unless(
            $access->canView(
                $request->user(),
                $wallet
            ),
            403
        );

        $perPage = min(
            100,
            max(
                1,
                (int) $request->integer(
                    'per_page',
                    20
                )
            )
        );

        $entries = $wallet
            ->entries()
            ->with('transfer')
            ->latest('id')
            ->paginate($perPage);

        return WalletEntryResource::collection(
            $entries
        );
    }
}
