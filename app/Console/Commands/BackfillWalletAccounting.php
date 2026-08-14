<?php

namespace App\Console\Commands;

use App\Enums\WalletAccountingPostingStatus;
use App\Enums\WalletTransferStatus;
use App\Models\WalletAccountingPosting;
use App\Models\WalletTransfer;
use App\Services\Wallet\WalletAccountingService;
use Illuminate\Console\Command;

class BackfillWalletAccounting extends Command
{
    protected $signature = 'wallet-accounting:backfill
        {--all : Process every completed Wallet transfer}
        {--from-transfer-id= : Process completed transfers from this ID onward}
        {--retry-failed : Retry only failed Wallet accounting postings}';

    protected $description =
        'Backfill or retry Wallet-to-Financial-Ledger accounting postings';

    public function handle(
        WalletAccountingService $service
    ): int {
        $all = (bool) $this->option('all');
        $from = $this->option('from-transfer-id');
        $retryFailed = (bool) $this->option('retry-failed');

        if (! $all && ! $from && ! $retryFailed) {
            $this->error(
                'Choose --all, --from-transfer-id=ID, or --retry-failed.'
            );

            return self::INVALID;
        }

        $query = WalletTransfer::query()
            ->where(
                'status',
                WalletTransferStatus::Completed->value
            )
            ->orderBy('id');

        if ($retryFailed) {
            $failedIds = WalletAccountingPosting::query()
                ->where(
                    'status',
                    WalletAccountingPostingStatus::Failed->value
                )
                ->pluck('wallet_transfer_id');

            $query->whereIn('id', $failedIds);
        } elseif ($from) {
            $query->where(
                'id',
                '>=',
                (int) $from
            );
        }

        $processed = 0;
        $posted = 0;
        $skipped = 0;
        $failed = 0;

        $query->chunkById(
            200,
            function ($transfers) use (
                $service,
                &$processed,
                &$posted,
                &$skipped,
                &$failed
            ): void {
                foreach ($transfers as $transfer) {
                    $processed++;

                    try {
                        $posting = $service->process(
                            $transfer
                        );

                        if (
                            $posting->status
                            === WalletAccountingPostingStatus::Posted
                        ) {
                            $posted++;
                        } elseif (
                            $posting->status
                            === WalletAccountingPostingStatus::Skipped
                        ) {
                            $skipped++;
                        } else {
                            $failed++;
                        }
                    } catch (\Throwable $exception) {
                        $failed++;

                        $this->warn(
                            sprintf(
                                'Transfer #%d failed: %s',
                                $transfer->getKey(),
                                $exception->getMessage()
                            )
                        );
                    }
                }
            }
        );

        $this->table(
            [
                'Processed',
                'Posted',
                'Skipped',
                'Failed',
            ],
            [[
                $processed,
                $posted,
                $skipped,
                $failed,
            ]]
        );

        return $failed > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
