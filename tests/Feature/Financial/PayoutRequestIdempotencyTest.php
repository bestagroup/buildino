<?php

namespace Tests\Feature\Financial;

use App\Enums\WalletPayoutStatus;
use App\Enums\WalletTransferType;
use App\Models\BuildingBankAccount;
use App\Models\ProviderBankAccount;
use App\Models\ProviderPayoutRequest;
use App\Models\WalletPayoutRequest;
use App\Models\WalletTransfer;
use App\Services\Wallet\ProviderPayoutService;
use App\Services\Wallet\WalletPayoutService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class PayoutRequestIdempotencyTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBuildingDomainData;

    public function test_building_payout_retry_returns_same_request_and_locks_balance_once(): void
    {
        $graph = $this->createBuildingGraph();
        $manager = $this->createUser();
        $support = $this->createUser();
        $bankAccount = $this->buildingBankAccount(
            $graph['building']->id,
            $support->id,
            'IR000000000000000000000011'
        );

        $wallets = app(WalletService::class);
        $wallet = $wallets->walletFor($graph['building']);

        $wallets->credit(
            $wallet,
            1_000_000,
            WalletTransferType::TopUp,
            'payout-idempotency-building-topup'
        );

        $service = app(WalletPayoutService::class);
        $key = 'building-payout-idempotency-1';

        $first = $service->request(
            $graph['building'],
            $bankAccount,
            $manager,
            400_000,
            $key
        );

        $second = $service->request(
            $graph['building']->fresh(),
            $bankAccount->fresh(),
            $manager->fresh(),
            400_000,
            $key
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(
            400_000,
            (int) $wallet->fresh()->locked_balance
        );

        $this->assertSame(
            1,
            WalletPayoutRequest::query()
                ->where('requested_by', $manager->id)
                ->where('idempotency_key', $key)
                ->count()
        );
    }

    public function test_building_payout_idempotency_key_cannot_be_reused_for_different_operation(): void
    {
        $graph = $this->createBuildingGraph();
        $manager = $this->createUser();
        $support = $this->createUser();
        $bankAccount = $this->buildingBankAccount(
            $graph['building']->id,
            $support->id,
            'IR000000000000000000000012'
        );

        $wallets = app(WalletService::class);
        $wallet = $wallets->walletFor($graph['building']);

        $wallets->credit(
            $wallet,
            1_000_000,
            WalletTransferType::TopUp,
            'payout-idempotency-building-conflict-topup'
        );

        $service = app(WalletPayoutService::class);
        $key = 'building-payout-idempotency-conflict';

        $service->request(
            $graph['building'],
            $bankAccount,
            $manager,
            300_000,
            $key
        );

        try {
            $service->request(
                $graph['building']->fresh(),
                $bankAccount->fresh(),
                $manager->fresh(),
                350_000,
                $key
            );

            $this->fail('A conflicting idempotency key must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'idempotency_key',
                $exception->errors()
            );
        }

        $this->assertSame(
            300_000,
            (int) $wallet->fresh()->locked_balance
        );
    }

    public function test_building_payout_state_transitions_are_idempotent_and_debit_once(): void
    {
        $graph = $this->createBuildingGraph();
        $manager = $this->createUser();
        $support = $this->createUser();
        $bankAccount = $this->buildingBankAccount(
            $graph['building']->id,
            $support->id,
            'IR000000000000000000000013'
        );

        $wallets = app(WalletService::class);
        $wallet = $wallets->walletFor($graph['building']);

        $wallets->credit(
            $wallet,
            900_000,
            WalletTransferType::TopUp,
            'payout-idempotency-building-paid-topup'
        );

        $service = app(WalletPayoutService::class);

        $payout = $service->request(
            $graph['building'],
            $bankAccount,
            $manager,
            500_000,
            'building-payout-state-idempotency'
        );

        $approved = $service->approve($payout, $support);
        $approvedAgain = $service->approve($approved->fresh(), $support);

        $this->assertSame($approved->id, $approvedAgain->id);
        $this->assertSame(WalletPayoutStatus::Approved, $approvedAgain->status);

        $paid = $service->markPaid(
            $approvedAgain->fresh(),
            $support,
            'BANK-BUILDING-500'
        );

        $paidAgain = $service->markPaid(
            $paid->fresh(),
            $support,
            'BANK-BUILDING-500'
        );

        $this->assertSame($paid->id, $paidAgain->id);
        $this->assertSame(WalletPayoutStatus::Paid, $paidAgain->status);
        $this->assertSame(400_000, (int) $wallet->fresh()->balance);
        $this->assertSame(0, (int) $wallet->fresh()->locked_balance);

        $this->assertSame(
            1,
            WalletTransfer::query()
                ->where(
                    'idempotency_key',
                    "wallet-payout:{$payout->id}:paid"
                )
                ->count()
        );
    }

    public function test_provider_payout_retry_returns_same_request_and_rejects_semantic_conflict(): void
    {
        $provider = $this->createUser();
        $support = $this->createUser();
        $bankAccount = ProviderBankAccount::query()->create([
            'user_id' => $provider->id,
            'bank_name' => 'Test Bank',
            'account_holder_name' => 'Provider',
            'iban' => 'IR000000000000000000000021',
            'is_default' => true,
            'is_verified' => true,
            'is_active' => true,
            'verified_by' => $support->id,
            'verified_at' => now(),
        ]);

        $wallets = app(WalletService::class);
        $wallet = $wallets->walletFor($provider, 'IRR');

        $wallets->credit(
            $wallet,
            800_000,
            WalletTransferType::TopUp,
            'payout-idempotency-provider-topup'
        );

        $service = app(ProviderPayoutService::class);
        $key = 'provider-payout-idempotency-1';

        $first = $service->request(
            $provider,
            $bankAccount,
            300_000,
            'IRR',
            $key
        );

        $second = $service->request(
            $provider->fresh(),
            $bankAccount->fresh(),
            300_000,
            'IRR',
            $key
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(300_000, (int) $wallet->fresh()->locked_balance);
        $this->assertSame(
            1,
            ProviderPayoutRequest::query()
                ->where('requested_by', $provider->id)
                ->where('idempotency_key', $key)
                ->count()
        );

        $this->expectException(ValidationException::class);

        $service->request(
            $provider->fresh(),
            $bankAccount->fresh(),
            350_000,
            'IRR',
            $key
        );
    }

    public function test_management_payout_create_contract_generates_retry_key(): void
    {
        $this->assertSame(
            'management-wallet-payout',
            config(
                'management_crud.resources.wallet-payouts.create.idempotency_key_prefix'
            )
        );
    }

    private function buildingBankAccount(
        int $buildingId,
        int $verifiedBy,
        string $iban
    ): BuildingBankAccount {
        return BuildingBankAccount::query()->create([
            'building_id' => $buildingId,
            'bank_name' => 'Test Bank',
            'account_holder_name' => 'Building',
            'iban' => $iban,
            'is_default' => true,
            'is_verified' => true,
            'is_active' => true,
            'verified_by' => $verifiedBy,
            'verified_at' => now(),
        ]);
    }
}
