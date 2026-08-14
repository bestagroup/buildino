<?php

namespace Tests\Feature\Financial;

use App\Enums\WalletPayoutStatus;
use App\Enums\WalletReconciliationStatus;
use App\Enums\WalletTransferType;
use App\Models\ProviderBankAccount;
use App\Models\Wallet;
use App\Services\ServiceMarketplace\PlatformWalletAccountService;
use App\Services\Wallet\ProviderPayoutService;
use App\Services\Wallet\WalletReconciliationService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class ProviderSettlementReconciliationFlowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBuildingDomainData;

    public function test_provider_payout_locks_then_debits_only_after_support_marks_paid(): void
    {
        $provider = $this->createUser();
        $payer = $this->createUser();
        $support = $this->createUser();

        $wallets = app(WalletService::class);

        $payerWallet = $wallets->walletFor(
            $payer,
            'IRR'
        );

        $providerWallet = $wallets->walletFor(
            $provider,
            'IRR'
        );

        $wallets->credit(
            $payerWallet,
            1_000_000,
            WalletTransferType::TopUp,
            'provider-settlement-payer-topup',
            null,
            $payer
        );

        $wallets->transfer(
            $payerWallet,
            $providerWallet,
            900_000,
            WalletTransferType::ServiceProviderPayment,
            'provider-earned-service-income',
            null,
            $provider,
            'Service provider earned income'
        );

        $account = ProviderBankAccount::query()->create([
            'user_id' => $provider->id,
            'bank_name' => 'Test Bank',
            'account_holder_name' => 'Provider',
            'iban' => 'IR000000000000000000000001',
            'is_default' => true,
            'is_verified' => true,
            'is_active' => true,
            'verified_by' => $support->id,
            'verified_at' => now(),
        ]);

        $service = app(
            ProviderPayoutService::class
        );

        $payout = $service->request(
            $provider,
            $account,
            600_000,
            'IRR'
        );

        $this->assertSame(
            WalletPayoutStatus::Pending,
            $payout->status
        );

        $this->assertSame(
            900_000,
            (int) $providerWallet->fresh()->balance
        );

        $this->assertSame(
            600_000,
            (int) $providerWallet->fresh()->locked_balance
        );

        $reconciliation = app(
            WalletReconciliationService::class
        )->reconcile(
            $providerWallet->fresh(),
            $support
        );

        $this->assertSame(
            WalletReconciliationStatus::Matched,
            $reconciliation->status
        );

        $this->assertSame(
            600_000,
            (int) $reconciliation->expected_locked_balance
        );

        $service->approve(
            $payout,
            $support
        );

        $paid = $service->markPaid(
            $payout->fresh(),
            $support,
            'BANK-REF-1001'
        );

        $this->assertSame(
            WalletPayoutStatus::Paid,
            $paid->status
        );

        $this->assertSame(
            300_000,
            (int) $providerWallet->fresh()->balance
        );

        $this->assertSame(
            0,
            (int) $providerWallet->fresh()->locked_balance
        );

        $this->assertDatabaseHas(
            'wallet_transfers',
            [
                'idempotency_key' =>
                    "provider-payout:{$payout->id}:paid",
                'type' =>
                    WalletTransferType::ProviderPayout->value,
                'amount' => 600_000,
            ]
        );

        /*
         * Paid callback is idempotent.
         */
        $service->markPaid(
            $payout->fresh(),
            $support,
            'BANK-REF-1001'
        );

        $this->assertSame(
            1,
            \App\Models\WalletTransfer::query()
                ->where(
                    'idempotency_key',
                    "provider-payout:{$payout->id}:paid"
                )
                ->count()
        );

        $after = app(
            WalletReconciliationService::class
        )->reconcile(
            $providerWallet->fresh(),
            $support
        );

        $this->assertSame(
            WalletReconciliationStatus::Matched,
            $after->status
        );

        $this->assertSame(
            0,
            (int) $after->expected_locked_balance
        );
    }

    public function test_rejected_provider_payout_unlocks_without_debiting_balance(): void
    {
        $provider = $this->createUser();
        $support = $this->createUser();

        $wallets = app(WalletService::class);

        $wallet = $wallets->walletFor(
            $provider
        );

        $wallets->credit(
            $wallet,
            500_000,
            WalletTransferType::TopUp,
            'provider-reject-topup',
            null,
            $provider
        );

        $account = ProviderBankAccount::query()->create([
            'user_id' => $provider->id,
            'account_holder_name' => 'Provider',
            'iban' => 'IR000000000000000000000002',
            'is_default' => true,
            'is_verified' => true,
            'is_active' => true,
            'verified_by' => $support->id,
            'verified_at' => now(),
        ]);

        $service = app(
            ProviderPayoutService::class
        );

        $payout = $service->request(
            $provider,
            $account,
            200_000
        );

        $this->assertSame(
            200_000,
            (int) $wallet->fresh()->locked_balance
        );

        $rejected = $service->reject(
            $payout,
            $support,
            'Bank account review required'
        );

        $this->assertSame(
            WalletPayoutStatus::Rejected,
            $rejected->status
        );

        $this->assertSame(
            500_000,
            (int) $wallet->fresh()->balance
        );

        $this->assertSame(
            0,
            (int) $wallet->fresh()->locked_balance
        );

        $reconciliation = app(
            WalletReconciliationService::class
        )->reconcile(
            $wallet->fresh(),
            $support
        );

        $this->assertSame(
            WalletReconciliationStatus::Matched,
            $reconciliation->status
        );
    }

    public function test_platform_wallet_reconciliation_detects_balance_tampering_without_mutating_wallet(): void
    {
        $admin = $this->createUser();

        $wallet = app(
            PlatformWalletAccountService::class
        )->marketplaceWallet('IRR');

        $sourceUser = $this->createUser();

        $sourceWallet = app(
            WalletService::class
        )->walletFor(
            $sourceUser,
            'IRR'
        );

        app(WalletService::class)->credit(
            $sourceWallet,
            200_000,
            WalletTransferType::TopUp,
            'platform-reconciliation-source-topup',
            null,
            $sourceUser
        );

        app(WalletService::class)->transfer(
            $sourceWallet,
            $wallet,
            100_000,
            WalletTransferType::PlatformCommission,
            'platform-reconciliation-commission',
            null,
            $admin
        );

        $service = app(
            WalletReconciliationService::class
        );

        $matched = $service->reconcile(
            $wallet->fresh(),
            $admin
        );

        $this->assertSame(
            WalletReconciliationStatus::Matched,
            $matched->status
        );

        Wallet::query()
            ->whereKey($wallet->id)
            ->update([
                'balance' => 100_001,
            ]);

        $mismatch = $service->reconcile(
            $wallet->fresh(),
            $admin
        );

        $this->assertSame(
            WalletReconciliationStatus::Mismatch,
            $mismatch->status
        );

        $this->assertSame(
            1,
            (int) $mismatch->balance_difference
        );

        /*
         * Reconciliation is diagnostic only.
         * It must never "fix" monetary balances automatically.
         */
        $this->assertSame(
            100_001,
            (int) $wallet->fresh()->balance
        );
    }

}
