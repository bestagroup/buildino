<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class ServiceMarketplacePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'service-finance.configure',
            'service-finance.quote',
            'service-finance.manage',
            'service-finance.settle',
            'platform-wallet.view',
        ];

        foreach ($permissions as $name) {
            Permission::query()->firstOrCreate(
                [
                    'name' => $name,
                ],
                [
                    'display_name' => $name,
                    'module' => 'service-finance',
                ]
            );
        }
    }
}
