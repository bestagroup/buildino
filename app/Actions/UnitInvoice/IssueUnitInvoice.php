<?php

namespace App\Actions\UnitInvoice;

use App\Models\UnitInvoice;
use App\Services\InvoiceService;

class IssueUnitInvoice
{
    public function __construct(private readonly InvoiceService $service) {}

    public function execute(UnitInvoice $invoice): UnitInvoice
    {
        return $this->service->issue($invoice);
    }
}
