<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class WalletOperationsPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'building-bank-accounts.view',
            'building-bank-accounts.create',
            'building-bank-accounts.verify',

            'wallet-payouts.view',
            'wallet-payouts.create',
            'wallet-payouts.approve',
            'wallet-payouts.reject',
            'wallet-payouts.pay',

            'building-bills.view',
            'building-bills.create',
            'building-bills.complete',
            'building-bills.fail',
        ];

        foreach ($names as $name) {
            Permission::query()->updateOrCreate(
                ['name' => $name],
                [
                    'display_name' => $name,
                    'module' => explode('.', $name)[0],
                ]
            );
        }
    }
}
