<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\UnitInvoice\IssueUnitInvoice;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdjustInvoicePenaltyRequest;
use App\Http\Resources\V1\UnitInvoiceResource;
use App\Models\UnitInvoice;
use App\Services\InvoicePenaltyService;

class InvoiceOperationController extends Controller
{
    public function issue(
        UnitInvoice $unitInvoice,
        IssueUnitInvoice $action
    ): UnitInvoiceResource {
        $this->authorize('issue', $unitInvoice);

        $invoice = $action->execute($unitInvoice);

        $invoice->load([
            'unit:id,floor_id,unit_number,title',
            'invoiceItems',
            'invoiceInstallments',
        ]);

        return new UnitInvoiceResource($invoice);
    }

    public function adjustPenalty(
        AdjustInvoicePenaltyRequest $request,
        UnitInvoice $unitInvoice,
        InvoicePenaltyService $service
    ): UnitInvoiceResource {
        $this->authorize('adjust', $unitInvoice);

        $invoice = $service->adjust(
            $unitInvoice,
            $request->user(),
            $request->validated('action'),
            (int) $request->validated('amount'),
            $request->validated('reason')
        );

        $invoice->load([
            'unit:id,floor_id,unit_number,title',
            'invoiceItems',
            'invoiceInstallments',
        ]);

        return new UnitInvoiceResource($invoice);
    }
}
