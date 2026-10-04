<?php

namespace Tests\Feature\Financial;

use App\Enums\FinancialAccountType;
use App\Enums\FinancialTransactionType;
use App\Enums\LedgerEntryType;
use App\Enums\WalletTransferType;
use App\Models\FinancialAccount;
use App\Services\FinancialLedgerService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class ImmutableLedgerTest extends TestCase
{
    use RefreshDatabase, CreatesBuildingDomainData;

    public function test_financial_transaction_and_entries_are_append_only(): void
    {
        $graph = $this->createBuildingGraph();
        $actor = $this->createUser();

        $cash = FinancialAccount::query()->create([
            'building_id' => $graph['building']->id,
            'code' => 'IMM-CASH',
            'title' => 'Cash',
            'type' => FinancialAccountType::Cash->value,
            'currency' => 'IRR',
            'is_active' => true,
        ]);

        $income = FinancialAccount::query()->create([
            'building_id' => $graph['building']->id,
            'code' => 'IMM-INCOME',
            'title' => 'Income',
            'type' => FinancialAccountType::Income->value,
            'currency' => 'IRR',
            'is_active' => true,
        ]);

        $transaction = app(FinancialLedgerService::class)->post(
            $graph['building'],
            $actor,
            [
                'transaction_type' => FinancialTransactionType::Income->value,
                'entries' => [
                    [
                        'financial_account_id' => $cash->id,
                        'entry_type' => LedgerEntryType::Debit->value,
                        'amount' => 100_000,
                    ],
                    [
                        'financial_account_id' => $income->id,
                        'entry_type' => LedgerEntryType::Credit->value,
                        'amount' => 100_000,
                    ],
                ],
            ]
        );

        try {
            $transaction->update([
                'description' => 'mutated',
            ]);

            $this->fail('Financial transaction mutation was accepted.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        $entry = $transaction
            ->financialLedgerEntries()
            ->firstOrFail();

        try {
            $entry->delete();

            $this->fail('Financial ledger entry deletion was accepted.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }
    }

    public function test_ledger_service_rejects_invalid_domain_entries_even_without_http_validation(): void
    {
        $graph = $this->createBuildingGraph();
        $actor = $this->createUser();

        $cash = FinancialAccount::query()->create([
            'building_id' => $graph['building']->id,
            'code' => 'DOMAIN-CASH',
            'title' => 'Cash',
            'type' => FinancialAccountType::Cash->value,
            'currency' => 'IRR',
            'is_active' => true,
        ]);

        $income = FinancialAccount::query()->create([
            'building_id' => $graph['building']->id,
            'code' => 'DOMAIN-INCOME',
            'title' => 'Income',
            'type' => FinancialAccountType::Income->value,
            'currency' => 'IRR',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(FinancialLedgerService::class)->post(
            $graph['building'],
            $actor,
            [
                'transaction_type' => FinancialTransactionType::Income->value,
                'entries' => [
                    [
                        'financial_account_id' => $cash->id,
                        'entry_type' => 'unsupported',
                        'amount' => 100_000,
                    ],
                    [
                        'financial_account_id' => $income->id,
                        'entry_type' => LedgerEntryType::Credit->value,
                        'amount' => 100_000,
                    ],
                ],
            ]
        );
    }

    public function test_ledger_service_rejects_inactive_accounts(): void
    {
        $graph = $this->createBuildingGraph();
        $actor = $this->createUser();

        $cash = FinancialAccount::query()->create([
            'building_id' => $graph['building']->id,
            'code' => 'LOCK-CASH',
            'title' => 'Cash',
            'type' => FinancialAccountType::Cash->value,
            'currency' => 'IRR',
            'is_active' => true,
        ]);

        $income = FinancialAccount::query()->create([
            'building_id' => $graph['building']->id,
            'code' => 'LOCK-INCOME',
            'title' => 'Income',
            'type' => FinancialAccountType::Income->value,
            'currency' => 'IRR',
            'is_active' => false,
        ]);

        $this->expectException(ValidationException::class);

        app(FinancialLedgerService::class)->post(
            $graph['building'],
            $actor,
            [
                'transaction_type' => FinancialTransactionType::Income->value,
                'entries' => [
                    [
                        'financial_account_id' => $cash->id,
                        'entry_type' => LedgerEntryType::Debit->value,
                        'amount' => 100_000,
                    ],
                    [
                        'financial_account_id' => $income->id,
                        'entry_type' => LedgerEntryType::Credit->value,
                        'amount' => 100_000,
                    ],
                ],
            ]
        );
    }

    public function test_wallet_entries_are_append_only(): void
    {
        $user = $this->createUser();
        $wallets = app(WalletService::class);
        $wallet = $wallets->walletFor($user, 'IRR');

        $transfer = $wallets->credit(
            $wallet,
            50_000,
            WalletTransferType::TopUp,
            'immutable-wallet-entry',
            null,
            $user
        );

        $entry = $transfer->entries()->firstOrFail();

        $this->expectException(LogicException::class);

        $entry->update([
            'amount' => 1,
        ]);
    }
}
