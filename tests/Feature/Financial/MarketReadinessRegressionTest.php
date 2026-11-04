<?php

namespace Tests\Feature\Financial;

use App\Enums\ExpensePayerResponsibility;
use App\Enums\FacilityWalletPayerSource;
use App\Enums\InvoiceStatus;
use App\Enums\RefundStatus;
use App\Enums\ReservationStatus;
use App\Enums\UnitChargePayerSource;
use App\Enums\WalletTransferType;
use App\Models\BuildingFacility;
use App\Models\FacilityReservation;
use App\Models\InvoiceWalletSettlement;
use App\Models\UnitInvoice;
use App\Models\UnitOccupancy;
use App\Services\Charge\PeriodResidentCount;
use App\Services\Charge\UnitChargePayerService;
use App\Services\Charge\WalletChargePeriodService;
use App\Services\ChargePeriodScheduleService;
use App\Services\Facility\FacilityWalletPaymentService;
use App\Services\FacilityReservationService;
use App\Services\InvoiceInstallmentService;
use App\Services\OccupancyService;
use App\Services\PaymentService;
use App\Services\Wallet\WalletService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class MarketReadinessRegressionTest extends TestCase
{
    use CreatesBuildingDomainData, RefreshDatabase;

    private function invoice(array $graph): UnitInvoice
    {
        return UnitInvoice::query()->create([
            'building_id' => $graph['building']->id, 'unit_id' => $graph['unit']->id,
            'invoice_number' => (string) Str::uuid(), 'issue_date' => today(), 'due_date' => today()->addMonth(),
            'subtotal' => 1000, 'total_amount' => 1000, 'paid_amount' => 0, 'outstanding_amount' => 1000,
            'status' => InvoiceStatus::Issued, 'payer_responsibility' => ExpensePayerResponsibility::Unit,
        ]);
    }

    public function test_wallet_settlement_rolls_back_transfer_when_settlement_insert_fails_and_retry_settles_installments_once(): void
    {
        $graph = $this->createBuildingGraph();
        $actor = $this->createUser();
        $invoice = $this->invoice($graph);
        app(InvoiceInstallmentService::class)->replace($invoice, $actor, [
            ['due_date' => today()->toDateString(), 'amount' => 400],
            ['due_date' => today()->addMonth()->toDateString(), 'amount' => 600],
        ]);
        $wallets = app(WalletService::class);
        $source = $wallets->walletFor($graph['unit']);
        $destination = $wallets->walletFor($graph['building']);
        $wallets->credit($source, 1000, WalletTransferType::TopUp, 'rollback-topup');
        $event = 'eloquent.created: '.InvoiceWalletSettlement::class;
        Event::listen($event, fn () => throw new \RuntimeException('Injected crash after settlement insert'));
        try {
            app(WalletChargePeriodService::class)->collectInvoice($invoice, $actor);
            $this->fail('Expected injected crash');
        } catch (\RuntimeException $e) {
            $this->assertSame('Injected crash after settlement insert', $e->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertSame(1000, (int) $source->fresh()->balance);
        $this->assertSame(0, (int) $destination->fresh()->balance);
        $this->assertSame(0, (int) $invoice->fresh()->paid_amount);
        $this->assertDatabaseCount('invoice_wallet_settlements', 0);
        $service = app(WalletChargePeriodService::class);
        $service->collectInvoice($invoice, $actor);
        $service->collectInvoice($invoice, $actor);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame(1000, (int) $invoice->invoiceInstallments()->sum('paid_amount'));
        $this->assertDatabaseCount('invoice_wallet_settlements', 1);
        $this->assertSame(1000, (int) $destination->fresh()->balance);
    }

    public function test_pending_gateway_amount_is_reserved_from_wallet_collection(): void
    {
        $graph = $this->createBuildingGraph();
        $actor = $this->createUser();
        $invoice = $this->invoice($graph);
        $payment = app(PaymentService::class)->createForInvoice($invoice, $actor, [
            'amount' => 600, 'method' => 'online', 'idempotency_key' => 'gateway-reservation',
        ]);
        $wallets = app(WalletService::class);
        $source = $wallets->walletFor($graph['unit']);
        $wallets->credit($source, 1000, WalletTransferType::TopUp, 'reserved-topup');
        app(WalletChargePeriodService::class)->collectInvoice($invoice, $actor);
        $this->assertSame(400, $invoice->fresh()->paid_amount);
        $this->assertSame(600, (int) $source->fresh()->balance);
        $payment->update(['status' => 'failed']);
        app(WalletChargePeriodService::class)->collectInvoice($invoice, $actor);
        $this->assertSame(1000, $invoice->fresh()->paid_amount);
    }

    public function test_personal_debt_cannot_be_collected_from_another_person_or_shared_unit_wallet(): void
    {
        $graph = $this->createBuildingGraph();
        $current = $this->createUser();
        $previous = $this->createUser();
        app(OccupancyService::class)->assign([
            'unit_id' => $graph['unit']->id, 'user_id' => $current->id, 'occupancy_type' => 'tenant',
            'starts_at' => today()->toDateString(), 'is_active' => true,
        ]);
        $invoice = $this->invoice($graph);
        $invoice->update(['payer_responsibility' => 'resident', 'payer_user_id' => $previous->id]);
        $wallets = app(WalletService::class);
        foreach ([$graph['unit'], $current] as $owner) {
            $wallet = $wallets->walletFor($owner);
            $wallets->credit($wallet, 1000, WalletTransferType::TopUp, 'payer-'.$wallet->id);
        }
        $service = app(WalletChargePeriodService::class);
        $service->collectInvoice($invoice, $current);
        $this->assertSame(0, $invoice->fresh()->paid_amount);
        app(UnitChargePayerService::class)->configure($graph['unit'], UnitChargePayerSource::UserWallet, $current);
        $service->collectInvoice($invoice, $current);
        $this->assertSame(0, $invoice->fresh()->paid_amount);
        $invoice->update(['payer_user_id' => $current->id]);
        $service->collectInvoice($invoice, $current);
        $this->assertSame(1000, $invoice->fresh()->paid_amount);
    }

    public function test_historical_count_survives_changes_and_ending_occupancy(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01'));
        $graph = $this->createBuildingGraph();
        $user = $this->createUser();
        $occupancies = app(OccupancyService::class);
        $occupancy = $occupancies->assign([
            'unit_id' => $graph['unit']->id, 'user_id' => $user->id, 'occupancy_type' => 'tenant',
            'starts_at' => '2026-08-01', 'resident_count' => 4, 'is_active' => true,
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-09-01'));
        $occupancies->update($occupancy, ['resident_count' => 2]);
        $this->travelTo(CarbonImmutable::parse('2026-10-01'));
        $occupancies->end($occupancy, $user, '2026-09-30');
        $counts = app(PeriodResidentCount::class);
        $this->assertSame(4, $counts->count($graph['unit'], CarbonImmutable::parse('2026-08-01'), CarbonImmutable::parse('2026-08-31')));
        $this->assertSame(2, $counts->count($graph['unit'], CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-30')));
        $this->assertSame(0, $counts->count($graph['unit'], CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31')));
    }

    public function test_unknown_historical_count_is_not_inferred_from_current_count(): void
    {
        $graph = $this->createBuildingGraph();
        UnitOccupancy::query()->create([
            'unit_id' => $graph['unit']->id, 'user_id' => $this->createUser()->id,
            'occupancy_type' => 'tenant', 'starts_at' => '2020-01-01', 'is_active' => true,
        ]);
        $this->expectException(ValidationException::class);
        app(PeriodResidentCount::class)->count($graph['unit'], CarbonImmutable::parse('2020-02-01'), CarbonImmutable::parse('2020-02-28'));
    }

    public function test_partial_period_edits_validate_merged_dates_and_overlap(): void
    {
        $graph = $this->createBuildingGraph();
        $user = $this->createUser();
        $service = app(ChargePeriodScheduleService::class);
        $first = $service->save($graph['building'], ['title' => 'First', 'period_start' => '2026-08-01', 'period_end' => '2026-08-31', 'due_date' => '2026-09-10'], $user);
        $second = $service->save($graph['building'], ['title' => 'Second', 'period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'due_date' => '2026-10-10'], $user);
        foreach ([['period_start' => '2026-08-15'], ['period_end' => '2026-08-20'], ['due_date' => '2026-09-01']] as $data) {
            try {
                $service->save($graph['building'], $data, $user, $second);
                $this->fail('Invalid period edit accepted');
            } catch (ValidationException) {
                $this->assertSame('2026-09-01', $second->fresh()->period_start->toDateString());
            }
        }
        $this->assertSame('Renamed', $service->save($graph['building'], ['title' => 'Renamed'], $user, $first)->title);
    }

    private function paidReservation(array $graph, $user): FacilityReservation
    {
        $buildingFacility = BuildingFacility::query()->create(['building_id' => $graph['building']->id, 'code' => 'pool', 'title' => 'Pool', 'is_active' => true]);
        $reservation = FacilityReservation::query()->create([
            'uuid' => (string) Str::uuid(), 'building_facility_id' => $buildingFacility->id,
            'unit_id' => $graph['unit']->id, 'user_id' => $user->id,
            'reservation_date' => today()->addDay(), 'start_time' => '10:00', 'end_time' => '11:00',
            'price' => 1000, 'final_amount' => 1000, 'status' => 'payment_pending', 'approval_type' => 'manual',
            'rule_snapshot' => ['cancellation_fee' => 500, 'refund_percentage' => 50],
        ]);
        $wallets = app(WalletService::class);
        $wallets->credit($wallets->walletFor($user), 1000, WalletTransferType::TopUp, 'reservation-topup');
        app(FacilityWalletPaymentService::class)->pay($reservation, $user, FacilityWalletPayerSource::UserWallet);

        return $reservation->fresh();
    }

    public function test_manager_rejection_refunds_full_payment_once(): void
    {
        $graph = $this->createBuildingGraph();
        $user = $this->createUser();
        $reservation = $this->paidReservation($graph, $user);
        $service = app(FacilityReservationService::class);
        $service->reject($reservation, $user);
        $service->reject($reservation, $user);
        $this->assertSame(ReservationStatus::Rejected, $reservation->fresh()->status);
        $this->assertSame(1000, (int) app(WalletService::class)->walletFor($user)->balance);
        $this->assertSame(1, $reservation->reservationCancellations()->count());
        $this->assertSame(RefundStatus::Refunded, $reservation->reservationCancellations()->first()->refund_status);
    }

    public function test_rejection_preserves_pending_refund_when_building_cannot_pay_and_retry_recovers(): void
    {
        $graph = $this->createBuildingGraph();
        $user = $this->createUser();
        $reservation = $this->paidReservation($graph, $user);
        $wallets = app(WalletService::class);
        $buildingWallet = $wallets->walletFor($graph['building']);
        $wallets->transfer($buildingWallet, $wallets->walletFor($graph['unit']), 1000, WalletTransferType::Refund, 'building-spent');
        $service = app(FacilityReservationService::class);
        $service->reject($reservation, $user);
        $this->assertSame(RefundStatus::Pending, $reservation->reservationCancellations()->first()->refund_status);
        $this->artisan('facilities:refund-pending')->assertExitCode(1);
        $wallets->credit($buildingWallet, 1000, WalletTransferType::TopUp, 'building-refund-funding');
        $this->artisan('facilities:refund-pending')->assertSuccessful();
        $this->artisan('facilities:refund-pending')->assertSuccessful();
        $this->assertSame(RefundStatus::Refunded, $reservation->reservationCancellations()->first()->refund_status);
        $this->assertSame(1000, (int) $wallets->walletFor($user)->balance);
    }
}
