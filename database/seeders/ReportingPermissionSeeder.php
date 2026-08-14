<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class ReportingPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'reports.dashboard.view',
            'reports.financial.view',
            'reports.receivables.view',
            'reports.operations.view',
            'reports.platform.view',
        ];

        foreach ($permissions as $name) {
            Permission::query()->firstOrCreate(
                [
                    'name' => $name,
                ],
                [
                    'display_name' => $name,
                    'module' => 'reports',
                ]
            );
        }
    }
}
