<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PaymentGatewayPermissionSeeder extends Seeder
{
    public function run(): void
    {
        Permission::query()->firstOrCreate(
            [
                'name' =>
                    'payments.gateway-events.view',
            ],
            [
                'display_name' =>
                    'payments.gateway-events.view',
                'module' => 'payments',
            ]
        );
    }
}
