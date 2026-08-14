<?php

namespace App\Events;

use App\Models\UnitInvoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceIssued
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly UnitInvoice $invoice) {}
}
