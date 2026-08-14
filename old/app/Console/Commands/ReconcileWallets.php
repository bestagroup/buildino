<?php

namespace App\Console\Commands;

use App\Enums\WalletReconciliationStatus;
use App\Models\PlatformWalletAccount;
use App\Models\Wallet;
use App\Services\Wallet\WalletReconciliationService;
use Illuminate\Console\Command;

class ReconcileWallets extends Command
{
    protected $signature = 'wallets:reconcile
        {--wallet= : Reconcile one Wallet ID}
        {--platform : Reconcile only platform-owned Wallets}';

    protected $description =
        'Create non-mutating reconciliation snapshots for Wallet balances and locks';

    public function handle(
        WalletReconciliationService $service
    ): int {
        $query = Wallet::query()
            ->orderBy('id');

        if ($this->option('wallet')) {
            $query->whereKey(
                (int) $this->option('wallet')
            );
        } elseif ($this->option('platform')) {
            $platformTypes = [
                (new PlatformWalletAccount())
                    ->getMorphClass(),
                PlatformWalletAccount::class,
            ];

            $query->whereIn(
                'owner_type',
                array_values(
                    array_unique($platformTypes)
                )
            );
        }

        $matched = 0;
        $mismatch = 0;
        $processed = 0;

        $query->chunkById(
            200,
            function ($wallets) use (
                $service,
                &$matched,
                &$mismatch,
                &$processed
            ): void {
                foreach ($wallets as $wallet) {
                    $result = $service->reconcile(
                        $wallet
                    );

                    $processed++;

                    if (
                        $result->status
                        === WalletReconciliationStatus::Matched
                    ) {
                        $matched++;
                    } else {
                        $mismatch++;
                    }
                }
            }
        );

        $this->table(
            ['Processed', 'Matched', 'Mismatch'],
            [[
                $processed,
                $matched,
                $mismatch,
            ]]
        );

        return $mismatch > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
