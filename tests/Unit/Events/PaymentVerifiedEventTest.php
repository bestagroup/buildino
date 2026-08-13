<?php

namespace Tests\Unit\Events;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Events\PaymentVerified;
use App\Models\UnitInvoice;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class PaymentVerifiedEventTest extends TestCase
{
    use DatabaseMigrations, CreatesBuildingDomainData;

    public function test_payment_verification_dispatches_domain_event(): void
    {
        Event::fake([
            PaymentVerified::class,
        ]);

        $graph = $this->createBuildingGraph();
        $user = $this->createUser();

        $invoice = UnitInvoice::query()->create([
            'building_id' => $graph['building']->id,
            'unit_id' => $graph['unit']->id,

            'invoice_number' => 'INV-EVENT-1',

            'issue_date' => now()->toDateString(),
            'due_date' => now()
                ->addDays(10)
                ->toDateString(),

            'subtotal' => 100000,
            'discount_amount' => 0,
            'penalty_amount' => 0,

            'total_amount' => 100000,
            'paid_amount' => 0,
            'outstanding_amount' => 100000,

            'status' => InvoiceStatus::Issued,
        ]);

        $service = app(
            PaymentService::class
        );

        /*
         * Use the real payment creation path so the payment
         * receives a valid full allocation before verification.
         */
        $payment = $service->createForInvoice(
            $invoice,
            $user,
            [
                'amount' => 100000,
                'method' => PaymentMethod::Manual,
                'description' => 'Payment event test',
            ]
        );

        $service->verify(
            $payment,
            $user
        );

        Event::assertDispatched(
            PaymentVerified::class,
            fn (PaymentVerified $event): bool =>
                $event->payment->is($payment)
        );

        $this->assertDatabaseHas(
            'unit_invoices',
            [
                'id' => $invoice->id,
                'paid_amount' => 100000,
                'outstanding_amount' => 0,
                'status' => InvoiceStatus::Paid->value,
            ]
        );
    }
}
