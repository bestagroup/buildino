<?php

namespace Tests\Unit\Domain;

use App\Enums\InvoiceStatus;
use App\Models\FinancialAuditLog;
use App\Models\UnitInvoice;
use App\Services\InvoiceAdjustmentService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class InvoiceAdjustmentServiceTest extends TestCase
{
    use RefreshDatabase, CreatesBuildingDomainData;

    private function invoice(): UnitInvoice
    {
        $graph = $this->createBuildingGraph();
        $invoice = UnitInvoice::query()->create([
            'building_id' => $graph['building']->id,
            'unit_id' => $graph['unit']->id,
            'invoice_number' => 'INV-'.uniqid(),
            'issue_date' => today(),
            'due_date' => today()->addDays(10),
            'status' => InvoiceStatus::Issued,
        ]);
        foreach (['Fixed charge' => 100000, 'Water bill share' => 50000] as $title => $amount) {
            $invoice->invoiceItems()->create([
                'title' => $title, 'quantity' => 1,
                'unit_amount' => $amount, 'total_amount' => $amount,
            ]);
        }

        return app(InvoiceService::class)->recalculate($invoice);
    }

    public function test_signed_adjustment_replaces_previous_value_and_survives_recalculation_without_affecting_other_units(): void
    {
        $invoice = $this->invoice();
        $other = $this->invoice();
        $actor = $this->createUser();
        $service = app(InvoiceAdjustmentService::class);
        $service->set($invoice, $actor, 20000, 0, 'Extra service');
        $this->assertSame(170000, $invoice->fresh()->total_amount);
        $service->set($invoice, $actor, 20000, 0, 'Extra service');
        $this->assertSame(1, FinancialAuditLog::where('action', 'invoice.adjustment.set')->count());
        $service->set($invoice, $actor, -30000, 20000, 'Credit');
        $result = app(InvoiceService::class)->recalculate($invoice);
        $this->assertSame(120000, $result->total_amount);
        $this->assertSame(150000, $result->subtotal);
        $this->assertSame(150000, $other->fresh()->total_amount);
        $service->set($invoice, $actor, 0, -30000, 'Remove credit');
        $this->assertSame(150000, $invoice->fresh()->total_amount);
    }

    public function test_credit_cannot_erase_a_confirmed_payment_and_rolls_back(): void
    {
        $invoice = $this->invoice();
        $invoice->update(['paid_amount' => 100000, 'outstanding_amount' => 50000, 'status' => InvoiceStatus::Partial]);
        try {
            app(InvoiceAdjustmentService::class)->set($invoice, $this->createUser(), -60000, 0, 'Credit');
            $this->fail('Credit below paid amount must fail.');
        } catch (ValidationException) {
            $this->assertSame(0, $invoice->fresh()->adjustment_amount);
            $this->assertSame(100000, $invoice->fresh()->paid_amount);
            $this->assertSame(150000, $invoice->fresh()->total_amount);
        }
    }

    public function test_credit_can_settle_the_remaining_balance(): void
    {
        $invoice = $this->invoice();
        $invoice->update(['paid_amount' => 100000, 'outstanding_amount' => 50000, 'status' => InvoiceStatus::Partial]);
        $result = app(InvoiceAdjustmentService::class)->set($invoice, $this->createUser(), -50000, 0, 'Credit');
        $this->assertSame(0, $result->outstanding_amount);
        $this->assertSame(InvoiceStatus::Paid, $result->status);
        $this->assertSame(100000, $result->paid_amount);
    }

    public function test_stale_edit_does_not_overwrite_another_adjustment(): void
    {
        $invoice = $this->invoice();
        $actor = $this->createUser();
        $service = app(InvoiceAdjustmentService::class);
        $service->set($invoice, $actor, 20000, 0, 'Extra');
        $this->expectException(ValidationException::class);
        $service->set($invoice, $actor, -10000, 0, 'Stale credit');
    }
}
