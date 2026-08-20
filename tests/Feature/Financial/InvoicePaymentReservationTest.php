<?php

namespace Tests\Feature\Financial;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\UnitInvoice;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class InvoicePaymentReservationTest extends TestCase
{
    use RefreshDatabase, CreatesBuildingDomainData;

    public function test_same_idempotency_key_returns_same_invoice_payment(): void
    {
        $graph = $this->createBuildingGraph();
        $payer = $this->createUser();
        $invoice = $this->invoice($graph, 500_000);

        $service = app(PaymentService::class);

        $first = $service->createForInvoice(
            $invoice,
            $payer,
            [
                'amount' => 200_000,
                'method' => PaymentMethod::Online->value,
                'idempotency_key' => 'invoice-payment-idem-1',
            ]
        );

        $second = $service->createForInvoice(
            $invoice->fresh(),
            $payer,
            [
                'amount' => 200_000,
                'method' => PaymentMethod::Online->value,
                'idempotency_key' => 'invoice-payment-idem-1',
            ]
        );

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('payment_allocations', 1);
    }

    public function test_idempotency_key_cannot_be_reused_for_different_amount(): void
    {
        $graph = $this->createBuildingGraph();
        $payer = $this->createUser();
        $invoice = $this->invoice($graph, 500_000);

        $service = app(PaymentService::class);

        $service->createForInvoice(
            $invoice,
            $payer,
            [
                'amount' => 200_000,
                'method' => PaymentMethod::Online->value,
                'idempotency_key' => 'invoice-payment-idem-2',
            ]
        );

        try {
            $service->createForInvoice(
                $invoice->fresh(),
                $payer,
                [
                    'amount' => 250_000,
                    'method' => PaymentMethod::Online->value,
                    'idempotency_key' => 'invoice-payment-idem-2',
                ]
            );

            $this->fail('Idempotency key reuse with different semantics was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'idempotency_key',
                $exception->errors()
            );
        }

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('payment_allocations', 1);
    }

    public function test_pending_payments_reserve_invoice_outstanding_amount(): void
    {
        $graph = $this->createBuildingGraph();
        $payer = $this->createUser();
        $invoice = $this->invoice($graph, 500_000);

        $service = app(PaymentService::class);

        $service->createForInvoice(
            $invoice,
            $payer,
            [
                'amount' => 400_000,
                'method' => PaymentMethod::Online->value,
                'idempotency_key' => 'invoice-reservation-1',
            ]
        );

        try {
            $service->createForInvoice(
                $invoice->fresh(),
                $payer,
                [
                    'amount' => 200_000,
                    'method' => PaymentMethod::Online->value,
                    'idempotency_key' => 'invoice-reservation-2',
                ]
            );

            $this->fail('Invoice outstanding amount was over-reserved.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $allowed = $service->createForInvoice(
            $invoice->fresh(),
            $payer,
            [
                'amount' => 100_000,
                'method' => PaymentMethod::Online->value,
                'idempotency_key' => 'invoice-reservation-3',
            ]
        );

        $this->assertSame(100_000, (int) $allowed->amount);
        $this->assertDatabaseCount('payments', 2);
    }

    private function invoice(array $graph, int $amount): UnitInvoice
    {
        return UnitInvoice::query()->create([
            'building_id' => $graph['building']->id,
            'unit_id' => $graph['unit']->id,
            'invoice_number' => 'INV-'.str()->upper(str()->random(12)),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'subtotal' => $amount,
            'discount_amount' => 0,
            'penalty_amount' => 0,
            'total_amount' => $amount,
            'paid_amount' => 0,
            'outstanding_amount' => $amount,
            'status' => InvoiceStatus::Issued,
        ]);
    }
}
