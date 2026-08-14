<?php

namespace App\Console\Commands;

use App\Enums\LedgerEntryType;
use App\Enums\WalletAccountingPostingStatus;
use App\Enums\WalletTransferStatus;
use App\Models\WalletAccountingPosting;
use App\Models\WalletTransfer;
use Illuminate\Console\Command;

class AuditWalletAccounting extends Command
{
    protected $signature =
        'wallet-accounting:audit';

    protected $description =
        'Audit completeness and balance of Wallet accounting bridge postings';

    public function handle(): int
    {
        $completedTransfers = WalletTransfer::query()
            ->where(
                'status',
                WalletTransferStatus::Completed->value
            )
            ->count();

        $postingCount =
            WalletAccountingPosting::query()
                ->count();

        $missingPostings =
            WalletTransfer::query()
                ->where(
                    'status',
                    WalletTransferStatus::Completed->value
                )
                ->whereDoesntHave(
                    'accountingPosting'
                )
                ->count();

        $failedPostings =
            WalletAccountingPosting::query()
                ->where(
                    'status',
                    WalletAccountingPostingStatus::Failed->value
                )
                ->count();

        $pendingPostings =
            WalletAccountingPosting::query()
                ->where(
                    'status',
                    WalletAccountingPostingStatus::Pending->value
                )
                ->count();

        $invalidPosted = 0;
        $unbalancedTransactions = 0;

        WalletAccountingPosting::query()
            ->with(
                'financialTransaction.financialLedgerEntries'
            )
            ->where(
                'status',
                WalletAccountingPostingStatus::Posted->value
            )
            ->chunkById(
                200,
                function ($postings) use (
                    &$invalidPosted,
                    &$unbalancedTransactions
                ): void {
                    foreach ($postings as $posting) {
                        $transaction =
                            $posting->financialTransaction;

                        if (! $transaction) {
                            $invalidPosted++;
                            continue;
                        }

                        if (
                            $transaction
                                ->financialLedgerEntries
                                ->count() < 2
                        ) {
                            $unbalancedTransactions++;
                            continue;
                        }

                        $totals = [];

                        foreach (
                            $transaction->financialLedgerEntries
                            as $entry
                        ) {
                            $currency = strtoupper(
                                $entry->currency
                            );

                            $totals[$currency] ??= [
                                'debit' => 0,
                                'credit' => 0,
                            ];

                            $type =
                                $entry->entry_type
                                    instanceof LedgerEntryType
                                    ? $entry
                                        ->entry_type
                                        ->value
                                    : $entry
                                        ->entry_type;

                            $totals[$currency][$type]
                                += (int) $entry->amount;
                        }

                        foreach ($totals as $total) {
                            if (
                                $total['debit'] <= 0
                                || $total['credit'] <= 0
                                || $total['debit']
                                    !== $total['credit']
                            ) {
                                $unbalancedTransactions++;
                                break;
                            }
                        }
                    }
                }
            );

        $skippedWithTransaction =
            WalletAccountingPosting::query()
                ->where(
                    'status',
                    WalletAccountingPostingStatus::Skipped->value
                )
                ->whereNotNull(
                    'financial_transaction_id'
                )
                ->count();

        $this->table(
            ['Check', 'Count'],
            [
                [
                    'Completed Wallet transfers',
                    $completedTransfers,
                ],
                [
                    'Accounting postings',
                    $postingCount,
                ],
                [
                    'Missing postings',
                    $missingPostings,
                ],
                [
                    'Failed postings',
                    $failedPostings,
                ],
                [
                    'Pending postings',
                    $pendingPostings,
                ],
                [
                    'Posted rows without ledger transaction',
                    $invalidPosted,
                ],
                [
                    'Unbalanced ledger transactions',
                    $unbalancedTransactions,
                ],
                [
                    'Skipped rows with ledger transaction',
                    $skippedWithTransaction,
                ],
            ]
        );

        $issues =
            $missingPostings
            + $failedPostings
            + $pendingPostings
            + $invalidPosted
            + $unbalancedTransactions
            + $skippedWithTransaction;

        return $issues > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
