<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\UnitInvoice\IssueUnitInvoice;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\UnitInvoiceResource;
use App\Models\UnitInvoice;

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
        ]);

        return new UnitInvoiceResource($invoice);
    }
}
