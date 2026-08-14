<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class FinalCompletionPermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (
            [
                'support-config.view',
                'support-config.manage',
            ]
            as $name
        ) {
            Permission::query()->firstOrCreate(
                ['name' => $name],
                [
                    'display_name' => $name,
                    'module' => 'support',
                ]
            );
        }
    }
}
