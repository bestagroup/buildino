<?php

namespace Tests\Feature\Financial;

use App\Enums\FinancialTransactionType;
use App\Enums\LedgerEntryType;
use App\Enums\WalletAccountingPostingStatus;
use App\Enums\WalletTransferType;
use App\Services\Wallet\BuildingWalletAccountingProfileService;
use App\Services\Wallet\WalletAccountingService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class WalletAccountingBridgeTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBuildingDomainData;

    public function test_charge_collection_posts_balanced_building_ledger_exactly_once(): void
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
            500_000,
            WalletTransferType::TopUp,
            'wallet-accounting-charge-source',
            null,
            $actor
        );

        $transfer = $wallets->transfer(
            $unitWallet,
            $buildingWallet,
            300_000,
            WalletTransferType::ChargeCollection,
            'wallet-accounting-charge-collection',
            null,
            $actor
        );

        $accounting = app(
            WalletAccountingService::class
        );

        $posting = $accounting->process(
            $transfer,
            $actor
        );

        $this->assertSame(
            WalletAccountingPostingStatus::Posted,
            $posting->status
        );

        $this->assertSame(
            'building_charge_collection',
            $posting->mapping_key
        );

        $transaction =
            $posting
                ->financialTransaction()
                ->with('financialLedgerEntries')
                ->firstOrFail();

        $this->assertSame(
            FinancialTransactionType::Income,
            $transaction->transaction_type
        );

        $this->assertSame(
            $transfer->getMorphClass(),
            $transaction->reference_type
        );

        $this->assertSame(
            $transfer->id,
            (int) $transaction->reference_id
        );

        $entries =
            $transaction->financialLedgerEntries;

        $this->assertCount(
            2,
            $entries
        );

        $this->assertSame(
            300_000,
            (int) $entries
                ->where(
                    'entry_type',
                    LedgerEntryType::Debit
                )
                ->sum('amount')
        );

        $this->assertSame(
            300_000,
            (int) $entries
                ->where(
                    'entry_type',
                    LedgerEntryType::Credit
                )
                ->sum('amount')
        );

        /*
         * Re-processing the same WalletTransfer is idempotent.
         */
        $again = $accounting->process(
            $transfer->fresh(),
            $actor
        );

        $this->assertSame(
            $posting->id,
            $again->id
        );

        $this->assertDatabaseCount(
            'financial_transactions',
            1
        );

        $this->assertDatabaseCount(
            'financial_ledger_entries',
            2
        );
    }

    public function test_bill_payment_and_building_payout_use_different_accounting_meanings(): void
    {
        $graph = $this->createBuildingGraph();
        $actor = $this->createUser();

        $wallets = app(WalletService::class);

        $buildingWallet = $wallets->walletFor(
            $graph['building'],
            'IRR'
        );

        $wallets->credit(
            $buildingWallet,
            1_000_000,
            WalletTransferType::Adjustment,
            'wallet-accounting-building-seed',
            null,
            $actor
        );

        $wallets->lockFunds(
            $buildingWallet,
            300_000
        );

        $billTransfer = $wallets->debitLocked(
            $buildingWallet,
            300_000,
            WalletTransferType::BillPayment,
            'wallet-accounting-bill-payment',
            null,
            $actor
        );

        $accounting = app(
            WalletAccountingService::class
        );

        $billPosting = $accounting->process(
            $billTransfer,
            $actor
        );

        $this->assertSame(
            WalletAccountingPostingStatus::Posted,
            $billPosting->status
        );

        $this->assertSame(
            'building_bill_payment',
            $billPosting->mapping_key
        );

        $this->assertSame(
            FinancialTransactionType::Expense,
            $billPosting
                ->financialTransaction
                ->transaction_type
        );

        $wallets->lockFunds(
            $buildingWallet->fresh(),
            200_000
        );

        $payoutTransfer = $wallets->debitLocked(
            $buildingWallet->fresh(),
            200_000,
            WalletTransferType::Payout,
            'wallet-accounting-building-payout',
            null,
            $actor
        );

        $payoutPosting = $accounting->process(
            $payoutTransfer,
            $actor
        );

        $this->assertSame(
            WalletAccountingPostingStatus::Posted,
            $payoutPosting->status
        );

        $this->assertSame(
            'building_wallet_to_bank',
            $payoutPosting->mapping_key
        );

        /*
         * Building payout is NOT an expense.
         * It is an asset transfer from Wallet to Bank Clearing.
         */
        $this->assertSame(
            FinancialTransactionType::Adjustment,
            $payoutPosting
                ->financialTransaction
                ->transaction_type
        );

        $profile = app(
            BuildingWalletAccountingProfileService::class
        )->forBuilding(
            $graph['building']
        );

        $billEntries = $billPosting
            ->financialTransaction
            ->financialLedgerEntries()
            ->get();

        $this->assertTrue(
            $billEntries->contains(
                fn ($entry) =>
                    (int) $entry->financial_account_id
                        === (int) $profile->bill_expense_account_id
                    && $entry->entry_type
                        === LedgerEntryType::Debit
            )
        );

        $payoutEntries = $payoutPosting
            ->financialTransaction
            ->financialLedgerEntries()
            ->get();

        $this->assertTrue(
            $payoutEntries->contains(
                fn ($entry) =>
                    (int) $entry->financial_account_id
                        === (int) $profile->bank_clearing_account_id
                    && $entry->entry_type
                        === LedgerEntryType::Debit
            )
        );
    }

    public function test_personal_provider_payout_is_audited_as_skipped_not_forced_into_building_ledger(): void
    {
        $provider = $this->createUser();
        $actor = $this->createUser();

        $wallets = app(WalletService::class);

        $providerWallet = $wallets->walletFor(
            $provider,
            'IRR'
        );

        $wallets->credit(
            $providerWallet,
            400_000,
            WalletTransferType::TopUp,
            'wallet-accounting-provider-source',
            null,
            $provider
        );

        $wallets->lockFunds(
            $providerWallet,
            250_000
        );

        $transfer = $wallets->debitLocked(
            $providerWallet,
            250_000,
            WalletTransferType::ProviderPayout,
            'wallet-accounting-provider-payout',
            null,
            $actor
        );

        $posting = app(
            WalletAccountingService::class
        )->process(
            $transfer,
            $actor
        );

        $this->assertSame(
            WalletAccountingPostingStatus::Skipped,
            $posting->status
        );

        $this->assertSame(
            'no_building_ledger_mapping',
            $posting->mapping_key
        );

        $this->assertNull(
            $posting->financial_transaction_id
        );

        $this->assertStringContainsString(
            'provider personal Wallet',
            (string) $posting->reason
        );
    }

    public function test_default_profile_provisions_system_accounts_for_building_currency(): void
    {
        $graph = $this->createBuildingGraph();

        $profile = app(
            BuildingWalletAccountingProfileService::class
        )->forBuilding(
            $graph['building']
        );

        $profile->load([
            'walletAssetAccount',
            'chargeCollectionCreditAccount',
            'facilityIncomeAccount',
            'billExpenseAccount',
            'bankClearingAccount',
        ]);

        $this->assertSame(
            'SYS_WALLET_ASSET',
            $profile->walletAssetAccount->code
        );

        $this->assertSame(
            'SYS_CHARGE_COLLECTION',
            $profile->chargeCollectionCreditAccount->code
        );

        $this->assertSame(
            'SYS_FACILITY_INCOME',
            $profile->facilityIncomeAccount->code
        );

        $this->assertSame(
            'SYS_BILL_EXPENSE',
            $profile->billExpenseAccount->code
        );

        $this->assertSame(
            'SYS_BANK_CLEARING',
            $profile->bankClearingAccount->code
        );

        $this->assertSame(
            'IRR',
            $profile->walletAssetAccount->currency
        );
    }
}
