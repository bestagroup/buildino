<?php

namespace Tests\Unit\Domain;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\UnitInvoice;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase, CreatesBuildingDomainData;

    public function test_verified_payment_updates_allocated_invoice(): void
    {
        $graph = $this->createBuildingGraph();
        $user = $this->createUser();

        $invoice = UnitInvoice::query()->create([
            'building_id' => $graph['building']->id,
            'unit_id' => $graph['unit']->id,
            'invoice_number' => 'INV-PAY-1',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'total_amount' => 200000,
            'paid_amount' => 0,
            'outstanding_amount' => 200000,
            'status' => InvoiceStatus::Issued,
        ]);

        $payment = Payment::query()->create([
            'uuid' => (string) str()->uuid(),
            'building_id' => $graph['building']->id,
            'payer_user_id' => $user->id,
            'payment_number' => 'PAY-1',
            'amount' => 120000,
            'currency' => 'IRR',
            'method' => PaymentMethod::Manual,
            'status' => PaymentStatus::Pending,
        ]);

        PaymentAllocation::query()->create([
            'payment_id' => $payment->id,
            'payable_type' => $invoice->getMorphClass(),
            'payable_id' => $invoice->id,
            'amount' => 120000,
        ]);

        app(PaymentService::class)->verify($payment, $user);

        $invoice->refresh();
        $payment->refresh();

        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame(120000, $invoice->paid_amount);
        $this->assertSame(80000, $invoice->outstanding_amount);
        $this->assertSame(InvoiceStatus::Partial, $invoice->status);
    }

    public function test_verifying_same_payment_twice_does_not_double_allocate(): void
    {
        $graph = $this->createBuildingGraph();
        $user = $this->createUser();

        $invoice = UnitInvoice::query()->create([
            'building_id' => $graph['building']->id,
            'unit_id' => $graph['unit']->id,
            'invoice_number' => 'INV-PAY-2',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'total_amount' => 100000,
            'paid_amount' => 0,
            'outstanding_amount' => 100000,
            'status' => InvoiceStatus::Issued,
        ]);

        $payment = Payment::query()->create([
            'uuid' => (string) str()->uuid(),
            'building_id' => $graph['building']->id,
            'payer_user_id' => $user->id,
            'payment_number' => 'PAY-2',
            'amount' => 100000,
            'currency' => 'IRR',
            'method' => PaymentMethod::Manual,
            'status' => PaymentStatus::Pending,
        ]);

        PaymentAllocation::query()->create([
            'payment_id' => $payment->id,
            'payable_type' => $invoice->getMorphClass(),
            'payable_id' => $invoice->id,
            'amount' => 100000,
        ]);

        $service = app(PaymentService::class);
        $service->verify($payment, $user);
        $service->verify($payment->fresh(), $user);

        $this->assertSame(100000, $invoice->fresh()->paid_amount);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }
}
