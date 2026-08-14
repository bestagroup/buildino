<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MarkProviderPayoutPaidRequest;
use App\Http\Requests\RejectProviderPayoutRequest;
use App\Http\Requests\StoreProviderPayoutRequest;
use App\Http\Resources\V1\ProviderPayoutResource;
use App\Models\ProviderBankAccount;
use App\Models\ProviderPayoutRequest;
use App\Services\Wallet\ProviderPayoutService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProviderPayoutController extends Controller
{
    public function mine(
        Request $request
    ): AnonymousResourceCollection {
        return ProviderPayoutResource::collection(
            ProviderPayoutRequest::query()
                ->where(
                    'provider_user_id',
                    $request->user()->getKey()
                )
                ->latest('id')
                ->paginate(20)
        );
    }

    public function adminIndex(
        Request $request,
        PermissionChecker $permissions
    ): AnonymousResourceCollection {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'provider-payouts.view',
                null
            ),
            403
        );

        return ProviderPayoutResource::collection(
            ProviderPayoutRequest::query()
                ->latest('id')
                ->paginate(20)
        );
    }

    public function store(
        StoreProviderPayoutRequest $request,
        ProviderPayoutService $service
    ) {
        $account = ProviderBankAccount::query()
            ->findOrFail(
                $request->integer(
                    'provider_bank_account_id'
                )
            );

        $payout = $service->request(
            $request->user(),
            $account,
            $request->integer('amount'),
            strtoupper(
                $request->validated('currency')
                    ?? 'IRR'
            )
        );

        return (new ProviderPayoutResource(
            $payout
        ))
            ->response()
            ->setStatusCode(201);
    }

    public function approve(
        Request $request,
        ProviderPayoutRequest $providerPayoutRequest,
        ProviderPayoutService $service,
        PermissionChecker $permissions
    ): ProviderPayoutResource {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'provider-payouts.approve',
                null
            ),
            403
        );

        return new ProviderPayoutResource(
            $service->approve(
                $providerPayoutRequest,
                $request->user()
            )
        );
    }

    public function reject(
        RejectProviderPayoutRequest $request,
        ProviderPayoutRequest $providerPayoutRequest,
        ProviderPayoutService $service,
        PermissionChecker $permissions
    ): ProviderPayoutResource {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'provider-payouts.reject',
                null
            ),
            403
        );

        return new ProviderPayoutResource(
            $service->reject(
                $providerPayoutRequest,
                $request->user(),
                $request->validated('reason')
            )
        );
    }

    public function markPaid(
        MarkProviderPayoutPaidRequest $request,
        ProviderPayoutRequest $providerPayoutRequest,
        ProviderPayoutService $service,
        PermissionChecker $permissions
    ): ProviderPayoutResource {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'provider-payouts.pay',
                null
            ),
            403
        );

        return new ProviderPayoutResource(
            $service->markPaid(
                $providerPayoutRequest,
                $request->user(),
                $request->validated(
                    'bank_reference'
                )
            )
        );
    }
}
