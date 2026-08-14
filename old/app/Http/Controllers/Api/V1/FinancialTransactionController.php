<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFinancialTransactionRequest;
use App\Http\Resources\V1\FinancialTransactionResource;
use App\Models\Building;
use App\Services\FinancialLedgerService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FinancialTransactionController extends Controller
{
    public function index(
        Request $request,
        Building $building,
        PermissionChecker $permissions
    ): AnonymousResourceCollection {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'financial-transactions.view',
                $building
            ),
            403
        );

        return FinancialTransactionResource::collection(
            $building->financialTransactions()
                ->with('financialLedgerEntries')
                ->latest('occurred_at')
                ->paginate(
                    min(
                        max($request->integer('per_page', 20), 1),
                        100
                    )
                )
                ->withQueryString()
        );
    }

    public function store(
        StoreFinancialTransactionRequest $request,
        Building $building,
        FinancialLedgerService $service,
        PermissionChecker $permissions
    ) {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'financial-transactions.create',
                $building
            ),
            403
        );

        $transaction = $service->post(
            $building,
            $request->user(),
            $request->validated()
        );

        $transaction->load('financialLedgerEntries');

        return (new FinancialTransactionResource($transaction))
            ->response()
            ->setStatusCode(201);
    }
}
