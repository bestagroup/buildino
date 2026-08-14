<?php

namespace Tests\Feature\Financial;

use App\Enums\OccupancyType;
use App\Enums\ServiceRequestPayerSource;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestWalletPaymentStatus;
use App\Enums\WalletTransferType;
use App\Models\BuildingServiceFinancialSetting;
use App\Models\ServiceRequest;
use App\Models\UnitOccupancy;
use App\Services\ServiceMarketplace\PlatformWalletAccountService;
use App\Services\ServiceMarketplace\ServiceRequestMarketplaceService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class ServiceMarketplaceWalletFlowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBuildingDomainData;

    public function test_service_payment_is_locked_then_split_between_provider_and_platform_on_confirmation(): void
    {
        $graph = $this->createBuildingGraph();

        $requester = $this->createUser();
        $provider = $this->createUser();

        $serviceRequest = $this->createAssignedRequest(
            $graph,
            $requester,
            $provider
        );

        $wallets = app(WalletService::class);

        $source = $wallets->walletFor(
            $requester,
            'IRR'
        );

        $wallets->credit(
            $source,
            1_000_000,
            WalletTransferType::TopUp,
            'service-flow-user-topup',
            null,
            $requester
        );

        $marketplace = app(
            ServiceRequestMarketplaceService::class
        );

        $quote = $marketplace->createQuote(
            $serviceRequest,
            1_000_000,
            'Electrical repair'
        );

        $this->assertSame(
            100_000,
            (int) $quote->commission_amount
        );

        $this->assertSame(
            900_000,
            (int) $quote->provider_amount
        );

        $payment = $marketplace->acceptQuote(
            $quote,
            $requester,
            ServiceRequestPayerSource::UserWallet
        );

        $this->assertSame(
            ServiceRequestWalletPaymentStatus::Locked,
            $payment->status
        );

        $this->assertSame(
            1_000_000,
            (int) $source->fresh()->balance
        );

        $this->assertSame(
            1_000_000,
            (int) $source->fresh()->locked_balance
        );

        $marketplace->start(
            $serviceRequest->fresh()
        );

        $marketplace->finish(
            $serviceRequest->fresh()
        );

        $this->assertSame(
            ServiceRequestStatus::AwaitingConfirmation,
            $serviceRequest->fresh()->status
        );

        $settled = $marketplace->confirmCompletion(
            $serviceRequest->fresh(),
            $requester
        );

        $providerWallet = $wallets->walletFor(
            $provider,
            'IRR'
        );

        $platformWallet = app(
            PlatformWalletAccountService::class
        )->marketplaceWallet('IRR');

        $this->assertSame(
            ServiceRequestWalletPaymentStatus::Settled,
            $settled->status
        );

        $this->assertSame(
            0,
            (int) $source->fresh()->balance
        );

        $this->assertSame(
            0,
            (int) $source->fresh()->locked_balance
        );

        $this->assertSame(
            900_000,
            (int) $providerWallet->fresh()->balance
        );

        $this->assertSame(
            100_000,
            (int) $platformWallet->fresh()->balance
        );

        $this->assertSame(
            ServiceRequestStatus::Completed,
            $serviceRequest->fresh()->status
        );

        $this->assertDatabaseHas(
            'wallet_transfers',
            [
                'idempotency_key' =>
                    "service-request:{$serviceRequest->id}:provider-settlement",
                'type' =>
                    WalletTransferType::ServiceProviderPayment->value,
                'amount' => 900_000,
            ]
        );

        $this->assertDatabaseHas(
            'wallet_transfers',
            [
                'idempotency_key' =>
                    "service-request:{$serviceRequest->id}:platform-commission",
                'type' =>
                    WalletTransferType::PlatformCommission->value,
                'amount' => 100_000,
            ]
        );

        /*
         * Completion confirmation is idempotent.
         */
        $again = $marketplace->confirmCompletion(
            $serviceRequest->fresh(),
            $requester
        );

        $this->assertSame(
            $settled->id,
            $again->id
        );

        $this->assertDatabaseCount(
            'service_request_wallet_payments',
            1
        );
    }

    public function test_cancelling_before_completion_releases_locked_service_funds(): void
    {
        $graph = $this->createBuildingGraph();

        $requester = $this->createUser();
        $provider = $this->createUser();

        $serviceRequest = $this->createAssignedRequest(
            $graph,
            $requester,
            $provider
        );

        $wallets = app(WalletService::class);

        $source = $wallets->walletFor(
            $requester,
            'IRR'
        );

        $wallets->credit(
            $source,
            500_000,
            WalletTransferType::TopUp,
            'service-cancel-user-topup',
            null,
            $requester
        );

        $marketplace = app(
            ServiceRequestMarketplaceService::class
        );

        $quote = $marketplace->createQuote(
            $serviceRequest,
            300_000
        );

        $payment = $marketplace->acceptQuote(
            $quote,
            $requester,
            ServiceRequestPayerSource::UserWallet
        );

        $this->assertSame(
            300_000,
            (int) $source->fresh()->locked_balance
        );

        $marketplace->cancel(
            $serviceRequest->fresh()
        );

        $this->assertSame(
            500_000,
            (int) $source->fresh()->balance
        );

        $this->assertSame(
            0,
            (int) $source->fresh()->locked_balance
        );

        $this->assertSame(
            ServiceRequestWalletPaymentStatus::Released,
            $payment->fresh()->status
        );

        $this->assertSame(
            ServiceRequestStatus::Cancelled,
            $serviceRequest->fresh()->status
        );

        $providerWallet = $wallets->walletFor(
            $provider,
            'IRR'
        );

        $platformWallet = app(
            PlatformWalletAccountService::class
        )->marketplaceWallet('IRR');

        $this->assertSame(
            0,
            (int) $providerWallet->fresh()->balance
        );

        $this->assertSame(
            0,
            (int) $platformWallet->fresh()->balance
        );
    }

    public function test_unit_wallet_can_fund_service_for_active_resident(): void
    {
        $graph = $this->createBuildingGraph();

        $requester = $this->createUser();
        $provider = $this->createUser();

        UnitOccupancy::query()->create([
            'unit_id' => $graph['unit']->id,
            'user_id' => $requester->id,
            'occupancy_type' =>
                OccupancyType::Resident,
            'starts_at' => now()->toDateString(),
            'is_primary' => true,
            'is_active' => true,
        ]);

        $serviceRequest = $this->createAssignedRequest(
            $graph,
            $requester,
            $provider
        );

        $wallets = app(WalletService::class);

        $unitWallet = $wallets->walletFor(
            $graph['unit'],
            'IRR'
        );

        $wallets->credit(
            $unitWallet,
            400_000,
            WalletTransferType::TopUp,
            'service-unit-wallet-topup',
            null,
            $requester
        );

        $marketplace = app(
            ServiceRequestMarketplaceService::class
        );

        $quote = $marketplace->createQuote(
            $serviceRequest,
            400_000
        );

        $payment = $marketplace->acceptQuote(
            $quote,
            $requester,
            ServiceRequestPayerSource::UnitWallet
        );

        $this->assertSame(
            $unitWallet->id,
            $payment->source_wallet_id
        );

        $this->assertSame(
            400_000,
            (int) $unitWallet->fresh()->locked_balance
        );

        $marketplace->cancel(
            $serviceRequest->fresh()
        );

        $this->assertSame(
            0,
            (int) $unitWallet->fresh()->locked_balance
        );

        $this->assertSame(
            400_000,
            (int) $unitWallet->fresh()->balance
        );
    }

    public function test_commission_is_calculated_from_server_side_building_setting(): void
    {
        $graph = $this->createBuildingGraph();

        $requester = $this->createUser();
        $provider = $this->createUser();

        BuildingServiceFinancialSetting::query()->create([
            'building_id' => $graph['building']->id,
            'platform_commission_bps' => 1250,
            'allow_user_wallet' => true,
            'allow_unit_wallet' => true,
            'is_active' => true,
        ]);

        $serviceRequest = $this->createAssignedRequest(
            $graph,
            $requester,
            $provider
        );

        $quote = app(
            ServiceRequestMarketplaceService::class
        )->createQuote(
            $serviceRequest,
            800_000
        );

        $this->assertSame(
            1250,
            (int) $quote->commission_rate_bps
        );

        $this->assertSame(
            100_000,
            (int) $quote->commission_amount
        );

        $this->assertSame(
            700_000,
            (int) $quote->provider_amount
        );
    }

    private function createAssignedRequest(
        array $graph,
        $requester,
        $provider
    ): ServiceRequest {
        return ServiceRequest::query()->create([
            'request_number' =>
                'SR-'.str()->upper(
                    str()->random(12)
                ),
            'building_id' =>
                $graph['building']->id,
            'unit_id' =>
                $graph['unit']->id,
            'requested_by' =>
                $requester->id,
            'type' => 'electrical',
            'priority' => 'normal',
            'status' =>
                ServiceRequestStatus::Assigned,
            'title' => 'Electrical service',
            'description' =>
                'Service marketplace wallet test',
            'assigned_to' =>
                $provider->id,
            'assigned_at' => now(),
        ]);
    }
}
