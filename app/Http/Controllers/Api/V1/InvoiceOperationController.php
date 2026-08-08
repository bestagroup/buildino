<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\UnitInvoice\IssueUnitInvoice;
use App\Http\Controllers\Controller;
use App\Models\UnitInvoice;
use Illuminate\Http\JsonResponse;

class InvoiceOperationController extends Controller
{
    public function issue(UnitInvoice $unitInvoice, IssueUnitInvoice $action): JsonResponse
    {
        $this->authorize('issue', $unitInvoice);
        return response()->json(['data' => $action->execute($unitInvoice)]);
    }
}
