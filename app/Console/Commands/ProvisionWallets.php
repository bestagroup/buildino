<?php

namespace App\Console\Commands;

use App\Models\Building;
use App\Models\Unit;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class ProvisionWallets extends Command
{
    protected $signature = 'wallets:provision
        {--users : Provision User wallets only}
        {--units : Provision Unit wallets only}
        {--buildings : Provision Building wallets only}';

    protected $description =
        'Provision missing wallets for existing users, units, and buildings';

    public function handle(
        WalletService $wallets
    ): int {
        $onlySpecified = $this->option('users')
            || $this->option('units')
            || $this->option('buildings');

        $counts = [
            'users' => 0,
            'units' => 0,
            'buildings' => 0,
        ];

        if (! $onlySpecified || $this->option('buildings')) {
            $counts['buildings'] = $this->provision(
                Building::query(),
                $wallets,
                fn (Building $building): string =>
                    strtoupper($building->currency ?: 'IRR')
            );
        }

        if (! $onlySpecified || $this->option('units')) {
            $counts['units'] = $this->provision(
                Unit::query()->with('floor.block.building'),
                $wallets,
                fn (Unit $unit): string =>
                    strtoupper(
                        $unit->floor?->block?->building?->currency
                            ?: 'IRR'
                    )
            );
        }

        if (! $onlySpecified || $this->option('users')) {
            $counts['users'] = $this->provision(
                User::query(),
                $wallets,
                fn (User $user): string => 'IRR'
            );
        }

        $this->table(
            ['Owner', 'Processed'],
            [
                ['Buildings', $counts['buildings']],
                ['Units', $counts['units']],
                ['Users', $counts['users']],
            ]
        );

        return self::SUCCESS;
    }

    private function provision(
        $query,
        WalletService $wallets,
        callable $currencyResolver
    ): int {
        $count = 0;

        $query
            ->orderBy('id')
            ->chunkById(
                200,
                function ($owners) use (
                    $wallets,
                    $currencyResolver,
                    &$count
                ): void {
                    foreach ($owners as $owner) {
                        /** @var Model $owner */
                        $wallets->walletFor(
                            $owner,
                            $currencyResolver($owner)
                        );

                        $count++;
                    }
                }
            );

        return $count;
    }
}
