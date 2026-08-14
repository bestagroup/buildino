<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class SystemHealthPermissionSeeder extends Seeder
{
    public function run(): void
    {
        Permission::query()->firstOrCreate(
            [
                'name' =>
                    'system.health.view',
            ],
            [
                'display_name' =>
                    'system.health.view',
                'module' =>
                    'system',
            ]
        );
    }
}
