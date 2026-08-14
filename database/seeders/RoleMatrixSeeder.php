<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use RuntimeException;

class RoleMatrixSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPermissionCatalog();

        $matrix = config(
            'role_matrix.roles',
            []
        );

        if (! is_array($matrix) || $matrix === []) {
            throw new RuntimeException(
                'Buildino role matrix configuration is empty.'
            );
        }

        foreach (
            $matrix
            as $name => $configuration
        ) {
            $role = Role::query()
                ->updateOrCreate(
                    [
                        'name' => $name,
                    ],
                    [
                        'display_name' =>
                            $configuration[
                                'display_name'
                            ] ?? $name,

                        'description' =>
                            $configuration[
                                'description'
                            ] ?? null,

                        'is_system' =>
                            (bool) (
                                $configuration[
                                    'is_system'
                                ] ?? true
                            ),
                    ]
                );

            $permissionNames =
                collect(
                    $configuration[
                        'permissions'
                    ] ?? []
                )
                    ->filter()
                    ->unique()
                    ->values();

            if (
                $permissionNames->contains(
                    '*'
                )
            ) {
                $role
                    ->permissions()
                    ->sync(
                        Permission::query()
                            ->pluck('id')
                            ->all()
                    );

                continue;
            }

            if (
                $permissionNames->isEmpty()
            ) {
                $role
                    ->permissions()
                    ->sync([]);

                continue;
            }

            $existing =
                Permission::query()
                    ->whereIn(
                        'name',
                        $permissionNames
                            ->all()
                    )
                    ->pluck(
                        'id',
                        'name'
                    );

            $missing =
                $permissionNames
                    ->reject(
                        fn (
                            string $permission
                        ): bool =>
                            $existing
                                ->has(
                                    $permission
                                )
                    )
                    ->values();

            if ($missing->isNotEmpty()) {
                throw new RuntimeException(
                    sprintf(
                        'Role [%s] references missing permissions: %s',
                        $name,
                        $missing->implode(', ')
                    )
                );
            }

            $role
                ->permissions()
                ->sync(
                    $existing
                        ->values()
                        ->all()
                );
        }

        $this->command?->info(
            sprintf(
                'Buildino role matrix synchronized: %d roles.',
                count($matrix)
            )
        );
    }

    private function seedPermissionCatalog(): void
    {
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
    }
}
