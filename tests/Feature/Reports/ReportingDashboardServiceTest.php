<?php

namespace Tests\Feature\Reports;

use App\Enums\FacilityType;
use App\Enums\FacilityWalletPayerSource;
use App\Enums\InvoiceStatus;
use App\Enums\ReservationApprovalType;
use App\Enums\ReservationStatus;
use App\Enums\ServiceRequestPayerSource;
use App\Enums\ServiceRequestQuoteStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestWalletPaymentStatus;
use App\Enums\WalletTransferType;
use App\Models\BuildingFacility;
use App\Models\FacilityReservation;
use App\Models\PlatformWalletAccount;
use App\Models\ReservationWalletPayment;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestQuote;
use App\Models\ServiceRequestWalletPayment;
use App\Models\UnitInvoice;
use App\Services\Reports\BuildingReportService;
use App\Services\Reports\PlatformReportService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class ReportingDashboardServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBuildingDomainData;

    public function test_financial_summary_separates_receivables_and_wallet_cash_flow(): void
    {
        $graph = $this->createBuildingGraph();
        $actor = $this->createUser();

        $wallets = app(WalletService::class);

        $unitWallet = $wallets->walletFor(
            $graph['unit'],
            'IRR'
        );

        $buildingWallet = $wallets->walletFor(
            $graph['building'],
            'IRR'
        );

        $wallets->credit(
            $unitWallet,
            1_000_000,
            WalletTransferType::TopUp,
            'reporting-unit-topup',
            null,
            $actor
        );

        $wallets->transfer(
            $unitWallet,
            $buildingWallet,
            400_000,
            WalletTransferType::ChargeCollection,
            'reporting-charge-collection',
            null,
            $actor
        );

        $wallets->transfer(
            $unitWallet->fresh(),
            $buildingWallet->fresh(),
            100_000,
            WalletTransferType::FacilityFee,
            'reporting-facility-income',
            null,
            $actor
        );

        $wallets->lockFunds(
            $buildingWallet->fresh(),
            50_000
        );

        $wallets->debitLocked(
            $buildingWallet->fresh(),
            50_000,
            WalletTransferType::BillPayment,
            'reporting-building-bill',
            null,
            $actor
        );

        $this->invoice(
            $graph,
            'REPORT-FIN-1',
            500_000,
            400_000,
            100_000,
            now()->addDays(10)->toDateString(),
            InvoiceStatus::Partial
        );

        $date = now()->toDateString();

        $report = app(
            BuildingReportService::class
        )->financialSummary(
            $graph['building'],
            $date,
            $date
        );

        $this->assertSame(
            450_000,
            $report['wallet']['balance']
        );

        $this->assertSame(
            100_000,
            $report['receivables']['current_outstanding']
        );

        $this->assertSame(
            500_000,
            $report['cash_flow']['inflow']
        );

        $this->assertSame(
            50_000,
            $report['cash_flow']['outflow']
        );

        $this->assertSame(
            450_000,
            $report['cash_flow']['net']
        );

        $this->assertSame(
            400_000,
            $report['cash_flow']['charge_collections']
        );

        $this->assertSame(
            100_000,
            $report['cash_flow']['facility_income']
        );

        $this->assertSame(
            50_000,
            $report['cash_flow']['bill_payments']
        );
    }

    public function test_receivables_report_builds_aging_buckets_by_unit(): void
    {
        $graph = $this->createBuildingGraph();

        $this->invoice(
            $graph,
            'AGING-10',
            100_000,
            0,
            100_000,
            now()->subDays(10)->toDateString()
        );

        $this->invoice(
            $graph,
            'AGING-40',
            200_000,
            0,
            200_000,
            now()->subDays(40)->toDateString()
        );

        $this->invoice(
            $graph,
            'AGING-70',
            300_000,
            0,
            300_000,
            now()->subDays(70)->toDateString()
        );

        $this->invoice(
            $graph,
            'AGING-100',
            400_000,
            0,
            400_000,
            now()->subDays(100)->toDateString()
        );

        $this->invoice(
            $graph,
            'AGING-FUTURE',
            500_000,
            0,
            500_000,
            now()->addDays(5)->toDateString()
        );

        $report = app(
            BuildingReportService::class
        )->receivables(
            $graph['building']
        );

        $this->assertSame(
            1_500_000,
            $report['totals']['outstanding_amount']
        );

        $this->assertSame(
            500_000,
            $report['aging']['not_due']
        );

        $this->assertSame(
            100_000,
            $report['aging']['days_1_30']
        );

        $this->assertSame(
            200_000,
            $report['aging']['days_31_60']
        );

        $this->assertSame(
            300_000,
            $report['aging']['days_61_90']
        );

        $this->assertSame(
            400_000,
            $report['aging']['days_90_plus']
        );

        $this->assertSame(
            1,
            $report['totals']['unit_count']
        );
    }

    public function test_facility_and_service_reports_use_paid_and_settled_amounts(): void
    {
        $graph = $this->createBuildingGraph();

        $requester = $this->createUser();
        $provider = $this->createUser();

        $wallets = app(WalletService::class);

        $requesterWallet = $wallets->walletFor(
            $requester,
            'IRR'
        );

        $buildingWallet = $wallets->walletFor(
            $graph['building'],
            'IRR'
        );

        $providerWallet = $wallets->walletFor(
            $provider,
            'IRR'
        );

        $wallets->credit(
            $requesterWallet,
            1_000_000,
            WalletTransferType::TopUp,
            'reporting-operations-topup',
            null,
            $requester
        );

        $facility = BuildingFacility::query()->create([
            'building_id' => $graph['building']->id,
            'title' => 'Meeting Hall',
            'code' => 'MEETING-HALL',
            'type' => FacilityType::MeetingHall,
            'capacity' => 20,
            'default_price' => 120_000,
            'requires_payment' => true,
            'requires_approval' => false,
            'is_active' => true,
        ]);

        $reservation = FacilityReservation::query()->create([
            'uuid' => (string) Str::uuid(),
            'building_facility_id' => $facility->id,
            'unit_id' => $graph['unit']->id,
            'user_id' => $requester->id,
            'reservation_date' => now()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'price' => 120_000,
            'discount_amount' => 0,
            'final_amount' => 120_000,
            'status' => ReservationStatus::Approved,
            'approval_type' =>
                ReservationApprovalType::Automatic,
        ]);

        $facilityTransfer = $wallets->transfer(
            $requesterWallet->fresh(),
            $buildingWallet,
            120_000,
            WalletTransferType::FacilityFee,
            'reporting-facility-payment',
            $reservation,
            $requester
        );

        ReservationWalletPayment::query()->create([
            'facility_reservation_id' =>
                $reservation->id,
            'wallet_transfer_id' =>
                $facilityTransfer->id,
            'source_wallet_id' =>
                $requesterWallet->id,
            'building_wallet_id' =>
                $buildingWallet->id,
            'payer_source' =>
                FacilityWalletPayerSource::UserWallet,
            'amount' => 120_000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $serviceRequest = ServiceRequest::query()->create([
            'request_number' => 'SR-REPORT-1',
            'building_id' => $graph['building']->id,
            'unit_id' => $graph['unit']->id,
            'requested_by' => $requester->id,
            'type' => 'electrical',
            'priority' => 'normal',
            'status' => ServiceRequestStatus::Completed,
            'title' => 'Electrical Repair',
            'assigned_to' => $provider->id,
            'assigned_at' => now(),
            'completed_at' => now(),
        ]);

        $quote = ServiceRequestQuote::query()->create([
            'uuid' => (string) Str::uuid(),
            'service_request_id' =>
                $serviceRequest->id,
            'provider_user_id' =>
                $provider->id,
            'amount' => 500_000,
            'commission_rate_bps' => 1000,
            'commission_amount' => 50_000,
            'provider_amount' => 450_000,
            'status' =>
                ServiceRequestQuoteStatus::Accepted,
            'accepted_by' => $requester->id,
            'accepted_at' => now(),
        ]);

        $platformAccount =
            PlatformWalletAccount::query()->create([
                'uuid' => (string) Str::uuid(),
                'code' => 'service_marketplace',
                'title' => 'Service Marketplace',
                'currency' => 'IRR',
                'is_active' => true,
            ]);

        $platformWallet = $wallets->walletFor(
            $platformAccount,
            'IRR'
        );

        ServiceRequestWalletPayment::query()->create([
            'uuid' => (string) Str::uuid(),
            'service_request_id' =>
                $serviceRequest->id,
            'service_request_quote_id' =>
                $quote->id,
            'source_wallet_id' =>
                $requesterWallet->id,
            'provider_wallet_id' =>
                $providerWallet->id,
            'platform_wallet_id' =>
                $platformWallet->id,
            'payer_source' =>
                ServiceRequestPayerSource::UserWallet,
            'amount' => 500_000,
            'provider_amount' => 450_000,
            'commission_amount' => 50_000,
            'status' =>
                ServiceRequestWalletPaymentStatus::Settled,
            'locked_at' => now(),
            'settled_at' => now(),
        ]);

        $date = now()->toDateString();

        $facilityReport = app(
            BuildingReportService::class
        )->facilities(
            $graph['building'],
            $date,
            $date
        );

        $this->assertSame(
            120_000,
            $facilityReport['totals']['paid_amount']
        );

        $this->assertSame(
            1,
            $facilityReport['totals']['reservation_count']
        );

        $serviceReport = app(
            BuildingReportService::class
        )->services(
            $graph['building'],
            $date,
            $date
        );

        $this->assertSame(
            500_000,
            $serviceReport['marketplace']['gmv']
        );

        $this->assertSame(
            450_000,
            $serviceReport['marketplace']['provider_amount']
        );

        $this->assertSame(
            50_000,
            $serviceReport['marketplace']['platform_commission']
        );
    }

    public function test_platform_summary_reports_marketplace_commission_by_currency(): void
    {
        $graph = $this->createBuildingGraph();

        $requester = $this->createUser();
        $provider = $this->createUser();

        $wallets = app(WalletService::class);

        $requesterWallet = $wallets->walletFor(
            $requester,
            'IRR'
        );

        $providerWallet = $wallets->walletFor(
            $provider,
            'IRR'
        );

        $platformAccount =
            PlatformWalletAccount::query()->create([
                'uuid' => (string) Str::uuid(),
                'code' => 'service_marketplace',
                'title' => 'Service Marketplace',
                'currency' => 'IRR',
                'is_active' => true,
            ]);

        $platformWallet = $wallets->walletFor(
            $platformAccount,
            'IRR'
        );

        $serviceRequest = ServiceRequest::query()->create([
            'request_number' => 'SR-PLATFORM-REPORT-1',
            'building_id' => $graph['building']->id,
            'unit_id' => $graph['unit']->id,
            'requested_by' => $requester->id,
            'type' => 'cleaning',
            'priority' => 'normal',
            'status' => ServiceRequestStatus::Completed,
            'title' => 'Cleaning',
            'assigned_to' => $provider->id,
            'assigned_at' => now(),
            'completed_at' => now(),
        ]);

        $quote = ServiceRequestQuote::query()->create([
            'uuid' => (string) Str::uuid(),
            'service_request_id' => $serviceRequest->id,
            'provider_user_id' => $provider->id,
            'amount' => 800_000,
            'commission_rate_bps' => 1250,
            'commission_amount' => 100_000,
            'provider_amount' => 700_000,
            'status' => ServiceRequestQuoteStatus::Accepted,
            'accepted_by' => $requester->id,
            'accepted_at' => now(),
        ]);

        ServiceRequestWalletPayment::query()->create([
            'uuid' => (string) Str::uuid(),
            'service_request_id' => $serviceRequest->id,
            'service_request_quote_id' => $quote->id,
            'source_wallet_id' => $requesterWallet->id,
            'provider_wallet_id' => $providerWallet->id,
            'platform_wallet_id' => $platformWallet->id,
            'payer_source' => ServiceRequestPayerSource::UserWallet,
            'amount' => 800_000,
            'provider_amount' => 700_000,
            'commission_amount' => 100_000,
            'status' => ServiceRequestWalletPaymentStatus::Settled,
            'locked_at' => now(),
            'settled_at' => now(),
        ]);

        $wallets->credit(
            $platformWallet,
            100_000,
            WalletTransferType::PlatformCommission,
            'reporting-platform-wallet-credit',
            null,
            $requester
        );

        $date = now()->toDateString();

        $report = app(
            PlatformReportService::class
        )->summary(
            $date,
            $date,
            'IRR'
        );

        $this->assertSame(
            800_000,
            $report['service_marketplace']['gmv']
        );

        $this->assertSame(
            100_000,
            $report['service_marketplace']['platform_commission']
        );

        $this->assertSame(
            0.125,
            $report['service_marketplace']['effective_commission_ratio']
        );

        $this->assertSame(
            100_000,
            $report['platform_wallets']['balance']
        );
    }

    private function invoice(
        array $graph,
        string $number,
        int $total,
        int $paid,
        int $outstanding,
        string $dueDate,
        InvoiceStatus $status = InvoiceStatus::Issued
    ): UnitInvoice {
        return UnitInvoice::query()->create([
            'building_id' =>
                $graph['building']->id,
            'unit_id' =>
                $graph['unit']->id,
            'invoice_number' => $number,
            'issue_date' =>
                now()->toDateString(),
            'due_date' => $dueDate,
            'subtotal' => $total,
            'discount_amount' => 0,
            'penalty_amount' => 0,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'outstanding_amount' =>
                $outstanding,
            'status' => $status,
        ]);
    }
}
