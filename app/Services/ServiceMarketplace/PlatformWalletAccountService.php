<?php

namespace App\Services\ServiceMarketplace;

use App\Models\PlatformWalletAccount;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PlatformWalletAccountService
{
    public function __construct(
        private readonly WalletService $wallets
    ) {
    }

    public function marketplaceWallet(
        string $currency
    ): Wallet {
        $currency = strtoupper($currency);

        $account = PlatformWalletAccount::query()
            ->firstOrCreate(
                [
                    'code' => 'service_marketplace',
                    'currency' => $currency,
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'title' => 'Service Marketplace Treasury',
                    'is_active' => true,
                ]
            );

        if (! $account->is_active) {
            throw ValidationException::withMessages([
                'platform_wallet' =>
                    'Service marketplace treasury account is inactive.',
            ]);
        }

        return $this->wallets->walletFor(
            $account,
            $currency
        );
    }
}
