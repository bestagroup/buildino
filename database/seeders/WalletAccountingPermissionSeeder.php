<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class WalletAccountingPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'wallet-accounting.view',
            'wallet-accounting.configure',
            'wallet-accounting.post',
        ];

        foreach ($permissions as $name) {
            Permission::query()->firstOrCreate(
                [
                    'name' => $name,
                ],
                [
                    'display_name' => $name,
                    'module' => 'wallet-accounting',
                ]
            );
        }
    }
}
