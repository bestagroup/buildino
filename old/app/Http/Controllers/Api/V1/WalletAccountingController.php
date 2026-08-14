<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBuildingWalletAccountingProfileRequest;
use App\Http\Resources\V1\BuildingWalletAccountingProfileResource;
use App\Http\Resources\V1\WalletAccountingPostingResource;
use App\Models\Building;
use App\Models\WalletAccountingPosting;
use App\Models\WalletTransfer;
use App\Services\Wallet\BuildingWalletAccountingProfileService;
use App\Services\Wallet\WalletAccountingService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;

class WalletAccountingController extends Controller
{
    public function profile(
        Request $request,
        Building $building,
        BuildingWalletAccountingProfileService $service,
        PermissionChecker $permissions
    ): BuildingWalletAccountingProfileResource {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'wallet-accounting.view',
                $building
            ),
            403
        );

        $profile = $service->forBuilding(
            $building
        );

        $profile->load([
            'walletAssetAccount',
            'chargeCollectionCreditAccount',
            'facilityIncomeAccount',
            'billExpenseAccount',
            'bankClearingAccount',
        ]);

        return new BuildingWalletAccountingProfileResource(
            $profile
        );
    }

    public function updateProfile(
        UpdateBuildingWalletAccountingProfileRequest $request,
        Building $building,
        BuildingWalletAccountingProfileService $service,
        PermissionChecker $permissions
    ): BuildingWalletAccountingProfileResource {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'wallet-accounting.configure',
                $building
            ),
            403
        );

        $profile = $service->update(
            $building,
            $request->validated()
        );

        $profile->load([
            'walletAssetAccount',
            'chargeCollectionCreditAccount',
            'facilityIncomeAccount',
            'billExpenseAccount',
            'bankClearingAccount',
        ]);

        return new BuildingWalletAccountingProfileResource(
            $profile
        );
    }

    public function posting(
        Request $request,
        WalletTransfer $walletTransfer,
        PermissionChecker $permissions
    ): WalletAccountingPostingResource {
        $posting = WalletAccountingPosting::query()
            ->with('financialTransaction')
            ->where(
                'wallet_transfer_id',
                $walletTransfer->getKey()
            )
            ->firstOrFail();

        $scope = $posting->building;

        abort_unless(
            $permissions->allows(
                $request->user(),
                'wallet-accounting.view',
                $scope
            ),
            403
        );

        return new WalletAccountingPostingResource(
            $posting
        );
    }

    public function post(
        Request $request,
        WalletTransfer $walletTransfer,
        WalletAccountingService $service,
        PermissionChecker $permissions
    ): WalletAccountingPostingResource {
        $existing = WalletAccountingPosting::query()
            ->where(
                'wallet_transfer_id',
                $walletTransfer->getKey()
            )
            ->first();

        $scope = $existing?->building;

        abort_unless(
            $permissions->allows(
                $request->user(),
                'wallet-accounting.post',
                $scope
            ),
            403
        );

        $posting = $service->process(
            $walletTransfer,
            $request->user()
        );

        $posting->load(
            'financialTransaction'
        );

        return new WalletAccountingPostingResource(
            $posting
        );
    }
}
