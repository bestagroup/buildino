<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class ReportExportPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'generated-reports.view',
            'generated-reports.create',
            'generated-reports.update',
            'generated-reports.delete',
        ];

        foreach ($permissions as $name) {
            Permission::query()->firstOrCreate(
                ['name' => $name],
                [
                    'display_name' => $name,
                    'module' => 'generated-reports',
                ]
            );
        }
    }
}
