<?php

namespace Tests\Unit\Domain;

use App\Enums\InvoiceStatus;
use App\Models\InvoiceItem;
use App\Models\UnitInvoice;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase, CreatesBuildingDomainData;

    public function test_invoice_is_recalculated_from_items_discount_and_penalty(): void
    {
        $graph = $this->createBuildingGraph();

        $invoice = UnitInvoice::query()->create([
            'building_id' => $graph['building']->id,
            'unit_id' => $graph['unit']->id,
            'invoice_number' => 'INV-001',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'discount_amount' => 10000,
            'penalty_amount' => 5000,
            'status' => InvoiceStatus::Draft,
        ]);

        InvoiceItem::query()->create([
            'unit_invoice_id' => $invoice->id,
            'title' => 'Charge',
            'quantity' => 1,
            'unit_amount' => 100000,
            'total_amount' => 100000,
        ]);

        InvoiceItem::query()->create([
            'unit_invoice_id' => $invoice->id,
            'title' => 'Water',
            'quantity' => 1,
            'unit_amount' => 50000,
            'total_amount' => 50000,
        ]);

        $result = app(InvoiceService::class)->recalculate($invoice);

        $this->assertSame(150000, $result->subtotal);
        $this->assertSame(145000, $result->total_amount);
        $this->assertSame(145000, $result->outstanding_amount);
    }

    public function test_only_draft_invoice_can_be_issued(): void
    {
        $graph = $this->createBuildingGraph();

        $invoice = UnitInvoice::query()->create([
            'building_id' => $graph['building']->id,
            'unit_id' => $graph['unit']->id,
            'invoice_number' => 'INV-002',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'status' => InvoiceStatus::Paid,
        ]);

        $this->expectException(ValidationException::class);

        app(InvoiceService::class)->issue($invoice);
    }
}
