<?php

namespace Tests\Unit\Domain;

use App\Http\Requests\StoreUnitInvoiceRequest;
use App\Models\UnitInvoice;
use App\Services\InvoiceCreditService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\Reports\BuildingReportService;
use App\Services\Web\BuildingAtGlanceService;
use App\Services\Web\PortalDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesChargePeriodScenario;
use Tests\TestCase;

final class InvoiceCreditServiceTest extends TestCase
{
    use CreatesChargePeriodScenario, RefreshDatabase;

    private function manual(array $graph, $actor, int $amount, bool $issue = true): UnitInvoice
    {
        $service = app(InvoiceService::class);
        $invoice = $service->createManual($graph['unit'], $actor, [
            'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(10)->toDateString(),
            'items' => [['title' => $amount < 0 ? 'Opening credit' : 'Opening debt', 'quantity' => 1, 'unit_amount' => $amount]],
        ]);

        return $issue ? $service->issue($invoice) : $invoice;
    }

    public function test_credit_reduces_actual_payable_without_fabricating_payment_or_wallet_funds(): void
    {
        [$graph, $actor] = $this->chargePeriodScenario();
        $debit = $this->manual($graph, $actor, 300000);
        $credit = $this->manual($graph, $actor, -200000);
        $this->assertSame(-200000, $credit->total_amount);
        $this->assertSame(0, $credit->outstanding_amount);
        $this->assertSame(0, $credit->credit_remaining_amount);
        $this->assertSame(100000, $debit->fresh()->total_amount);
        $this->assertSame(100000, $debit->fresh()->outstanding_amount);
        $this->assertSame(0, $debit->fresh()->paid_amount);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('wallet_entries', 0);
        app(InvoiceCreditService::class)->apply($graph['unit']->id, $graph['building']->id);
        app(InvoiceService::class)->recalculate($debit);
        $this->assertSame(100000, $debit->fresh()->outstanding_amount);
        $report = app(BuildingReportService::class)->financialSummary($graph['building'], now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());
        $this->assertSame(100000, $report['receivables']['period_invoiced']);
        $this->assertDatabaseCount('invoice_credit_allocations', 1);
        $this->assertDatabaseHas('invoice_credit_allocations', ['credit_invoice_id' => $credit->id, 'debit_invoice_id' => $debit->id, 'amount' => 200000]);
    }

    public function test_unused_credit_carries_forward_and_never_crosses_units_or_buildings(): void
    {
        [$graph, $actor] = $this->chargePeriodScenario();
        $credit = $this->manual($graph, $actor, -500000);
        $other = $this->createBuildingGraph();
        $otherDebt = $this->manual($other, $actor, 600000);
        $first = $this->manual($graph, $actor, 300000);
        $this->assertSame(0, $first->outstanding_amount);
        $this->assertSame('paid', $first->status->value);
        $this->assertSame(200000, $credit->fresh()->credit_remaining_amount);
        $second = $this->manual($graph, $actor, 250000);
        $this->assertSame(50000, $second->outstanding_amount);
        $this->assertSame(600000, $otherDebt->fresh()->outstanding_amount);
        $this->assertSame(0, $credit->fresh()->credit_remaining_amount);
        $this->assertDatabaseCount('invoice_credit_allocations', 2);
    }

    public function test_manual_debt_and_credit_are_included_with_period_debt_in_resident_and_manager_totals(): void
    {
        [$graph, $actor, , $period, , $periods] = $this->chargePeriodScenario();
        $this->manual($graph, $actor, 200000);
        $this->manual($graph, $actor, -50000);
        $preview = $periods->preview($period);
        $this->assertSame(150000, $preview['prior_debt_total']);
        $periods->calculate($period, $preview['review_token']);
        $periods->issue($period, $actor);
        $this->assertSame(250000, (int) UnitInvoice::where('unit_id', $graph['unit']->id)->sum('outstanding_amount'));
        $this->assertSame(250000, app(PortalDashboardService::class)->resident($actor)['stats']['outstanding_total']);
        $this->assertSame(250000, app(BuildingAtGlanceService::class)->report($graph['building'])['totals']['debt']);
    }

    public function test_credit_issuance_rolls_back_when_a_payment_is_pending(): void
    {
        [$graph, $actor] = $this->chargePeriodScenario();
        $debit = $this->manual($graph, $actor, 300000);
        app(PaymentService::class)->createForInvoice($debit, $actor, [
            'amount' => 100000, 'method' => 'bank_transfer', 'idempotency_key' => 'credit-reservation',
        ]);
        $credit = $this->manual($graph, $actor, -200000, false);
        try {
            app(InvoiceService::class)->issue($credit);
            $this->fail('Pending payment must be protected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('credit', $exception->errors());
        }
        $this->assertSame('draft', $credit->fresh()->status->value);
        $this->assertSame(300000, $debit->fresh()->outstanding_amount);
        $this->assertDatabaseCount('invoice_credit_allocations', 0);
    }

    public function test_draft_credit_has_no_effect_until_issued_and_repeated_issue_cannot_duplicate_it(): void
    {
        [$graph, $actor] = $this->chargePeriodScenario();
        $debit = $this->manual($graph, $actor, 300000);
        $credit = $this->manual($graph, $actor, -100000, false);
        $stale = $credit->fresh();
        $this->assertSame(300000, $debit->fresh()->outstanding_amount);
        app(InvoiceService::class)->issue($credit);
        try {
            app(InvoiceService::class)->issue($stale);
            $this->fail('Duplicate issuance must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $this->assertSame(200000, $debit->fresh()->outstanding_amount);
        $this->assertDatabaseCount('invoice_credit_allocations', 1);
    }

    public function test_negative_invoice_input_passes_request_validation(): void
    {
        $data = ['issue_date' => '2026-11-05', 'due_date' => '2026-11-10',
            'items' => [['title' => 'Credit', 'quantity' => 1, 'unit_amount' => -200000]]];
        $validator = validator($data, (new StoreUnitInvoiceRequest)->rules());
        $this->assertFalse($validator->fails());
    }

    public function test_cash_payment_after_credit_settles_only_the_net_due(): void
    {
        [$graph, $actor] = $this->chargePeriodScenario();
        $debit = $this->manual($graph, $actor, 300000);
        $this->manual($graph, $actor, -200000);
        $payments = app(PaymentService::class);
        $payment = $payments->createForInvoice($debit->fresh(), $actor, [
            'amount' => 100000, 'method' => 'bank_transfer', 'idempotency_key' => 'net-credit-payment',
        ]);
        $payments->verify($payment, $actor);
        app(InvoiceService::class)->recalculate($debit);
        $this->assertSame(100000, $debit->fresh()->paid_amount);
        $this->assertSame(0, $debit->fresh()->outstanding_amount);
        $this->assertSame(200000, $debit->fresh()->credit_applied_amount);
        $this->assertSame('paid', $debit->fresh()->status->value);
    }

    public function test_stale_draft_cannot_edit_or_delete_an_issued_credit_note(): void
    {
        [$graph, $actor] = $this->chargePeriodScenario();
        $credit = $this->manual($graph, $actor, -200000, false);
        $stale = $credit->fresh();
        $service = app(InvoiceService::class);
        $service->issue($credit);
        foreach ([fn () => $service->updateDraft($stale, ['description' => 'stale']), fn () => $service->voidDraft($stale)] as $operation) {
            try {
                $operation();
                $this->fail('Issued credit must be immutable.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('status', $exception->errors());
            }
        }
        $this->assertSame(200000, $credit->fresh()->credit_remaining_amount);
    }
}
