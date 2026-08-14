<?php

namespace Tests\Feature\Financial;

use App\Enums\ChargePolicyMode;
use App\Enums\InvoiceStatus;
use App\Enums\OccupancyType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UnitChargePayerSource;
use App\Enums\WalletTopUpStatus;
use App\Models\BuildingChargePolicy;
use App\Models\ChargePeriod;
use App\Models\UnitChargeSetting;
use App\Models\UnitInvoice;
use App\Models\UnitOccupancy;
use App\Services\PaymentService;
use App\Services\Wallet\WalletService;
use App\Services\Wallet\WalletTopUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class WalletTopUpFlowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBuildingDomainData;

    public function test_verified_external_payment_credits_unit_wallet_once_and_auto_collects_charge_debt(): void
    {
        $graph = $this->createBuildingGraph();
        $payer = $this->createUser();

        $this->enableAutoCollection(
            $graph['building']
        );

        $invoice = $this->createChargeDebt(
            $graph,
            300_000
        );

        $topUp = app(WalletTopUpService::class)
            ->create(
                $graph['building'],
                $payer,
                $graph['unit'],
                [
                    'amount' => 500_000,
                    'method' => PaymentMethod::Online,
                    'gateway' => 'test-gateway',
                    'idempotency_key' =>
                        'unit-topup-verify-once',
                ]
            );

        $payment = $topUp->payment()->firstOrFail();

        app(PaymentService::class)->verify(
            $payment,
            $payer
        );

        $wallets = app(WalletService::class);

        $unitWallet = $wallets->walletFor(
            $graph['unit']
        );

        $buildingWallet = $wallets->walletFor(
            $graph['building']
        );

        $this->assertSame(
            PaymentStatus::Paid,
            $payment->fresh()->status
        );

        $this->assertSame(
            WalletTopUpStatus::Credited,
            $topUp->fresh()->status
        );

        $this->assertSame(
            200_000,
            (int) $unitWallet->fresh()->balance
        );

        $this->assertSame(
            300_000,
            (int) $buildingWallet->fresh()->balance
        );

        $this->assertSame(
            InvoiceStatus::Paid,
            $invoice->fresh()->status
        );

        $this->assertSame(
            0,
            (int) $invoice->fresh()->outstanding_amount
        );

        $this->assertSame(
            300_000,
            (int) $topUp->fresh()
                ->retry_summary['collected_amount']
        );

        /*
         * Verification is idempotent:
         * no second top-up credit and no second invoice settlement.
         */
        app(PaymentService::class)->verify(
            $payment->fresh(),
            $payer
        );

        $this->assertSame(
            200_000,
            (int) $unitWallet->fresh()->balance
        );

        $this->assertSame(
            300_000,
            (int) $buildingWallet->fresh()->balance
        );

        $this->assertDatabaseCount(
            'invoice_wallet_settlements',
            1
        );

        $this->assertDatabaseHas(
            'wallet_transfers',
            [
                'idempotency_key' =>
                    "external-payment:{$payment->id}:wallet-topup",
                'amount' => 500_000,
                'type' => 'topup',
            ]
        );
    }

    public function test_user_wallet_topup_partially_collects_configured_unit_charge(): void
    {
        $graph = $this->createBuildingGraph();
        $payer = $this->createUser();

        UnitOccupancy::query()->create([
            'unit_id' => $graph['unit']->id,
            'user_id' => $payer->id,
            'occupancy_type' =>
                OccupancyType::Resident,
            'starts_at' => now()->toDateString(),
            'is_primary' => true,
            'is_active' => true,
        ]);

        UnitChargeSetting::query()->create([
            'unit_id' => $graph['unit']->id,
            'payer_source' =>
                UnitChargePayerSource::UserWallet,
            'payer_user_id' => $payer->id,
            'auto_collect' => true,
            'allow_partial' => true,
        ]);

        $this->enableAutoCollection(
            $graph['building']
        );

        $invoice = $this->createChargeDebt(
            $graph,
            300_000
        );

        $topUp = app(WalletTopUpService::class)
            ->create(
                $graph['building'],
                $payer,
                $payer,
                [
                    'amount' => 200_000,
                    'method' => PaymentMethod::Online,
                    'gateway' => 'test-gateway',
                    'idempotency_key' =>
                        'user-topup-partial-charge',
                ]
            );

        app(PaymentService::class)->verify(
            $topUp->payment()->firstOrFail(),
            $payer
        );

        $wallets = app(WalletService::class);

        $userWallet = $wallets->walletFor(
            $payer
        );

        $unitWallet = $wallets->walletFor(
            $graph['unit']
        );

        $buildingWallet = $wallets->walletFor(
            $graph['building']
        );

        $this->assertSame(
            0,
            (int) $userWallet->fresh()->balance
        );

        /*
         * User Wallet and Unit Wallet remain independent.
         */
        $this->assertSame(
            0,
            (int) $unitWallet->fresh()->balance
        );

        $this->assertSame(
            200_000,
            (int) $buildingWallet->fresh()->balance
        );

        $this->assertSame(
            InvoiceStatus::Partial,
            $invoice->fresh()->status
        );

        $this->assertSame(
            200_000,
            (int) $invoice->fresh()->paid_amount
        );

        $this->assertSame(
            100_000,
            (int) $invoice->fresh()->outstanding_amount
        );

        $this->assertSame(
            200_000,
            (int) $topUp->fresh()
                ->retry_summary['collected_amount']
        );
    }

    public function test_wallet_topup_creation_is_idempotent_by_payment_transaction_key(): void
    {
        $graph = $this->createBuildingGraph();
        $payer = $this->createUser();

        $service = app(
            WalletTopUpService::class
        );

        $data = [
            'amount' => 150_000,
            'method' => PaymentMethod::Online,
            'gateway' => 'test-gateway',
            'idempotency_key' =>
                'same-wallet-topup-request',
        ];

        $first = $service->create(
            $graph['building'],
            $payer,
            $graph['unit'],
            $data
        );

        $second = $service->create(
            $graph['building'],
            $payer,
            $graph['unit'],
            $data
        );

        $this->assertSame(
            $first->id,
            $second->id
        );

        $this->assertDatabaseCount(
            'wallet_topups',
            1
        );

        $this->assertDatabaseCount(
            'payments',
            1
        );

        $this->assertDatabaseCount(
            'payment_transactions',
            1
        );
    }

    private function enableAutoCollection(
        $building
    ): void {
        BuildingChargePolicy::query()->create([
            'building_id' => $building->id,
            'mode' => ChargePolicyMode::Fixed,
            'fixed_monthly_amount' => 0,
            'auto_collect' => true,
            'allow_partial' => true,
            'is_active' => true,
        ]);
    }

    private function createChargeDebt(
        array $graph,
        int $amount
    ): UnitInvoice {
        $period = ChargePeriod::query()->create([
            'building_id' => $graph['building']->id,
            'title' => 'Wallet top-up retry period',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'due_date' => '2026-09-10',
            'status' => 'issued',
        ]);

        return UnitInvoice::query()->create([
            'building_id' => $graph['building']->id,
            'unit_id' => $graph['unit']->id,
            'charge_period_id' => $period->id,
            'invoice_number' =>
                'TOPUP-INV-'.str()->random(10),
            'issue_date' => now()->toDateString(),
            'due_date' => '2026-09-10',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
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
