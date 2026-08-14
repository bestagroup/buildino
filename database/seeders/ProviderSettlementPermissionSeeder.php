<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class ProviderSettlementPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'provider-bank-accounts.verify',
            'provider-payouts.view',
            'provider-payouts.approve',
            'provider-payouts.reject',
            'provider-payouts.pay',
            'wallet-reconciliation.view',
            'wallet-reconciliation.run',
        ];

        foreach ($permissions as $name) {
            Permission::query()->firstOrCreate(
                [
                    'name' => $name,
                ],
                [
                    'display_name' => $name,
                    'module' => 'provider-settlement',
                ]
            );
        }
    }
}
