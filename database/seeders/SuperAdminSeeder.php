<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Ensure all project permissions exist
        |--------------------------------------------------------------------------
        */

        $this->call([
            PermissionSeeder::class,
            WalletOperationsPermissionSeeder::class,
            ServiceMarketplacePermissionSeeder::class,
            ProviderSettlementPermissionSeeder::class,
            WalletAccountingPermissionSeeder::class,
            ReportingPermissionSeeder::class,
            ReportExportPermissionSeeder::class,
            PaymentGatewayPermissionSeeder::class,
            SystemHealthPermissionSeeder::class,
            FinalCompletionPermissionSeeder::class,
        ]);

        DB::transaction(function (): void {

            /*
            |--------------------------------------------------------------------------
            | Super Admin User
            |--------------------------------------------------------------------------
            */

            $user = User::query()->updateOrCreate(
                [
                    'mobile' => '09128119938',
                ],
                [
                    'first_name' => 'حسین',
                    'last_name' => 'دیوان بیگی',

                    'email' => 'hosseindbk@gmail.com',

                    'mobile_verified_at' => now(),
                    'email_verified_at' => now(),

                    'password' => Hash::make(
                        'Buildino@1405'
                    ),

                    'is_active' => true,
                    'is_blocked' => false,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Super Admin Role
            |--------------------------------------------------------------------------
            */

            $role = Role::query()->updateOrCreate(
                [
                    'name' => 'superadmin',
                ],
                [
                    'display_name' => 'مدیر کل سامانه',

                    'description' =>
                        'دسترسی کامل و سراسری به تمامی بخش‌های سامانه Buildino.',

                    'is_system' => true,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Attach All Permissions
            |--------------------------------------------------------------------------
            */

            $permissionIds = Permission::query()
                ->pluck('id')
                ->all();

            $role->permissions()->sync(
                $permissionIds
            );

            /*
            |--------------------------------------------------------------------------
            | Global Role Assignment
            |--------------------------------------------------------------------------
            |
            | NULL Scope = Global Access
            |
            */

            UserRoleAssignment::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'role_id' => $role->id,

                    'scope_type' => null,
                    'scope_id' => null,
                ],
                [
                    'starts_at' => now(),
                    'ends_at' => null,

                    'is_active' => true,

                    'assigned_by' => null,
                ]
            );
        });

        $this->command?->newLine();

        $this->command?->info(
            'Buildino SuperAdmin created successfully.'
        );

        $this->command?->info(
            'Mobile: 09128119938'
        );

        $this->command?->info(
            'Email: hosseindbk@gmail.com'
        );

        $this->command?->info(
            'Password: Buildino@1405'
        );

        $this->command?->info(
            'Permissions: '
            . Permission::query()->count()
        );
    }
}
