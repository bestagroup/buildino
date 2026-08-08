<?php

namespace Database\Seeders;

use App\Models\Block;
use App\Models\Building;
use App\Models\Complex;
use App\Models\Floor;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class PostmanTestSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('PostmanTestSeeder may only run in local/testing environments.');
        }

        $this->call(PermissionSeeder::class);

        $user = User::query()->updateOrCreate(
            ['mobile' => '09120000000'],
            [
                'first_name' => 'Postman',
                'last_name' => 'Admin',
                'email' => 'postman@example.test',
                'mobile_verified_at' => now(),
                'email_verified_at' => now(),
                'password' => Hash::make('Postman@12345'),
                'is_active' => true,
                'is_blocked' => false,
            ],
        );

        $role = Role::query()->updateOrCreate(
            ['name' => 'postman-admin'],
            [
                'display_name' => 'Postman Admin',
                'description' => 'Local-only role for API integration tests.',
                'is_system' => true,
            ],
        );

        $role->permissions()->sync(Permission::query()->pluck('id'));

        UserRoleAssignment::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'role_id' => $role->id,
                'scope_type' => null,
                'scope_id' => null,
            ],
            [
                'is_active' => true,
                'assigned_by' => $user->id,
            ],
        );

        $complex = Complex::query()->firstOrCreate(
            ['code' => 'POSTMAN-COMPLEX'],
            [
                'title' => 'Postman Test Complex',
                'province' => 'Tehran',
                'city' => 'Tehran',
                'is_active' => true,
            ],
        );

        $building = Building::query()->firstOrCreate(
            ['code' => 'POSTMAN-BUILDING'],
            [
                'complex_id' => $complex->id,
                'title' => 'Postman Test Building',
                'timezone' => 'Asia/Tehran',
                'currency' => 'IRR',
                'is_active' => true,
            ],
        );

        $block = Block::query()->firstOrCreate(
            ['building_id' => $building->id, 'title' => 'A'],
            ['is_active' => true],
        );

        $floor = Floor::query()->firstOrCreate(
            ['block_id' => $block->id, 'floor_number' => 1],
            ['title' => 'First Floor'],
        );

        Unit::query()->firstOrCreate(
            ['floor_id' => $floor->id, 'unit_number' => '101'],
            [
                'title' => 'Postman Unit',
                'area' => 100,
                'usage_type' => 'residential',
                'is_active' => true,
            ],
        );
    }
}
