<?php

namespace App\Listeners;

use App\Events\WalletTransferCompleted;
use App\Services\Wallet\WalletAccountingService;
use Illuminate\Support\Facades\Log;

class PostWalletTransferToAccounting
{
    public function __construct(
        private readonly WalletAccountingService $accounting
    ) {
    }

    public function handle(
        WalletTransferCompleted $event
    ): void {
        try {
            $this->accounting->process(
                $event->walletTransferId
            );
        } catch (\Throwable $exception) {
            /*
             * Wallet transfer is already committed.
             * Never bubble an accounting exception back into the
             * money movement request.
             */
            Log::error(
                'Wallet accounting posting failed.',
                [
                    'wallet_transfer_id' =>
                        $event->walletTransferId,
                    'exception' =>
                        $exception::class,
                    'message' =>
                        $exception->getMessage(),
                ]
            );
        }
    }
}
