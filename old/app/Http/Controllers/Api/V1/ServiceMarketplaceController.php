<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ServiceRequestPayerSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptServiceRequestQuoteRequest;
use App\Http\Requests\StoreServiceRequestQuoteRequest;
use App\Http\Requests\UpdateBuildingServiceFinancialSettingRequest;
use App\Http\Resources\V1\ServiceRequestQuoteResource;
use App\Http\Resources\V1\ServiceRequestWalletPaymentResource;
use App\Http\Resources\V1\WalletResource;
use App\Models\Building;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestQuote;
use App\Services\ServiceMarketplace\BuildingServiceFinancialSettingService;
use App\Services\ServiceMarketplace\PlatformWalletAccountService;
use App\Services\ServiceMarketplace\ServiceRequestMarketplaceService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceMarketplaceController extends Controller
{
    public function showSetting(
        Request $request,
        Building $building,
        BuildingServiceFinancialSettingService $service,
        PermissionChecker $permissions
    ): JsonResponse {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'service-finance.configure',
                $building
            ),
            403
        );

        return response()->json([
            'data' => $service->forBuilding(
                $building
            ),
        ]);
    }

    public function updateSetting(
        UpdateBuildingServiceFinancialSettingRequest $request,
        Building $building,
        BuildingServiceFinancialSettingService $service,
        PermissionChecker $permissions
    ): JsonResponse {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'service-finance.configure',
                $building
            ),
            403
        );

        return response()->json([
            'data' => $service->update(
                $building,
                $request->validated()
            ),
        ]);
    }

    public function quote(
        StoreServiceRequestQuoteRequest $request,
        ServiceRequest $serviceRequest,
        ServiceRequestMarketplaceService $service,
        PermissionChecker $permissions
    ): ServiceRequestQuoteResource {
        $serviceRequest->loadMissing('building');

        $user = $request->user();

        $allowed =
            (int) $serviceRequest->assigned_to
                === (int) $user->getKey()
            || $permissions->allows(
                $user,
                'service-finance.quote',
                $serviceRequest->building
            );

        abort_unless(
            $allowed,
            403
        );

        $quote = $service->createQuote(
            $serviceRequest,
            (int) $request->validated('amount'),
            $request->validated('notes'),
            $request->validated('valid_until')
        );

        return new ServiceRequestQuoteResource(
            $quote
        );
    }

    public function acceptQuote(
        AcceptServiceRequestQuoteRequest $request,
        ServiceRequestQuote $serviceRequestQuote,
        ServiceRequestMarketplaceService $service
    ): ServiceRequestWalletPaymentResource {
        $serviceRequestQuote->loadMissing(
            'serviceRequest'
        );

        abort_unless(
            (int) $serviceRequestQuote
                ->serviceRequest
                ->requested_by
            === (int) $request
                ->user()
                ->getKey(),
            403
        );

        $payment = $service->acceptQuote(
            $serviceRequestQuote,
            $request->user(),
            ServiceRequestPayerSource::from(
                $request->validated('payer_source')
            )
        );

        return new ServiceRequestWalletPaymentResource(
            $payment
        );
    }

    public function start(
        Request $request,
        ServiceRequest $serviceRequest,
        ServiceRequestMarketplaceService $service,
        PermissionChecker $permissions
    ): JsonResponse {
        $serviceRequest->loadMissing('building');

        $allowed =
            (int) $serviceRequest->assigned_to
                === (int) $request->user()->getKey()
            || $permissions->allows(
                $request->user(),
                'service-finance.manage',
                $serviceRequest->building
            );

        abort_unless(
            $allowed,
            403
        );

        return response()->json([
            'data' => $service->start(
                $serviceRequest
            ),
        ]);
    }

    public function finish(
        Request $request,
        ServiceRequest $serviceRequest,
        ServiceRequestMarketplaceService $service,
        PermissionChecker $permissions
    ): JsonResponse {
        $serviceRequest->loadMissing('building');

        $allowed =
            (int) $serviceRequest->assigned_to
                === (int) $request->user()->getKey()
            || $permissions->allows(
                $request->user(),
                'service-finance.manage',
                $serviceRequest->building
            );

        abort_unless(
            $allowed,
            403
        );

        return response()->json([
            'data' => $service->finish(
                $serviceRequest
            ),
        ]);
    }

    public function confirm(
        Request $request,
        ServiceRequest $serviceRequest,
        ServiceRequestMarketplaceService $service,
        PermissionChecker $permissions
    ): ServiceRequestWalletPaymentResource {
        $serviceRequest->loadMissing('building');

        $allowed =
            (int) $serviceRequest->requested_by
                === (int) $request->user()->getKey()
            || $permissions->allows(
                $request->user(),
                'service-finance.settle',
                $serviceRequest->building
            );

        abort_unless(
            $allowed,
            403
        );

        return new ServiceRequestWalletPaymentResource(
            $service->confirmCompletion(
                $serviceRequest,
                $request->user()
            )
        );
    }

    public function cancel(
        Request $request,
        ServiceRequest $serviceRequest,
        ServiceRequestMarketplaceService $service,
        PermissionChecker $permissions
    ): JsonResponse {
        $serviceRequest->loadMissing('building');

        $allowed =
            (int) $serviceRequest->requested_by
                === (int) $request->user()->getKey()
            || $permissions->allows(
                $request->user(),
                'service-finance.manage',
                $serviceRequest->building
            );

        abort_unless(
            $allowed,
            403
        );

        return response()->json([
            'data' => $service->cancel(
                $serviceRequest
            ),
        ]);
    }

    public function platformWallet(
        Request $request,
        PlatformWalletAccountService $accounts,
        PermissionChecker $permissions
    ): WalletResource {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'platform-wallet.view',
                null
            ),
            403
        );

        $currency = strtoupper(
            $request->string(
                'currency',
                'IRR'
            )->toString()
        );

        return new WalletResource(
            $accounts->marketplaceWallet(
                $currency
            )
        );
    }
}
