<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class SubscriptionPermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'subscriptions.view',
            'subscriptions.manage',
        ] as $permission) {
            Permission::query()->firstOrCreate(
                ['name' => $permission],
                [
                    'display_name' => $permission,
                    'module' => 'subscriptions',
                ]
            );
        }
    }
}
