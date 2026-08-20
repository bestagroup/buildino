<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReplaceInvoiceInstallmentsRequest;
use App\Http\Resources\V1\InvoiceInstallmentResource;
use App\Models\UnitInvoice;
use App\Services\InvoiceAccessService;
use App\Services\InvoiceInstallmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class InvoiceInstallmentController extends Controller
{
    public function index(
        Request $request,
        UnitInvoice $unitInvoice,
        InvoiceAccessService $access
    ): AnonymousResourceCollection {
        abort_unless(
            $access->canView($request->user(), $unitInvoice),
            403
        );

        return InvoiceInstallmentResource::collection(
            $unitInvoice->invoiceInstallments()
                ->orderBy('installment_number')
                ->get()
        );
    }

    public function replace(
        ReplaceInvoiceInstallmentsRequest $request,
        UnitInvoice $unitInvoice,
        InvoiceInstallmentService $service
    ): AnonymousResourceCollection {
        $this->authorize('update', $unitInvoice);

        return InvoiceInstallmentResource::collection(
            $service->replace(
                $unitInvoice,
                $request->user(),
                $request->validated('installments')
            )
        );
    }

    public function destroy(
        Request $request,
        UnitInvoice $unitInvoice,
        InvoiceInstallmentService $service
    ): Response {
        $this->authorize('update', $unitInvoice);

        $service->cancel(
            $unitInvoice,
            $request->user()
        );

        return response()->noContent();
    }
}
