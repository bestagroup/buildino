<?php

namespace App\Observers;

use App\Models\Building;
use App\Models\Unit;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Illuminate\Database\Eloquent\Model;

final class ProvisionWalletObserver
{
    public function __construct(
        private readonly WalletService $wallets
    ) {
    }

    public function created(Model $owner): void
    {
        $currency = $this->currencyFor($owner);

        $this->wallets->walletFor(
            $owner,
            $currency
        );
    }

    private function currencyFor(Model $owner): string
    {
        if ($owner instanceof Building) {
            return strtoupper(
                $owner->currency ?: 'IRR'
            );
        }

        if ($owner instanceof Unit) {
            $owner->loadMissing(
                'floor.block.building'
            );

            return strtoupper(
                $owner->floor?->block?->building?->currency
                    ?: 'IRR'
            );
        }

        if ($owner instanceof User) {
            return 'IRR';
        }

        return 'IRR';
    }
}
