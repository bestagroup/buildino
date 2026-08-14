<?php

namespace Tests\Feature\Financial;

use App\Enums\OccupancyType;
use App\Enums\WalletTransferType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UnitOccupancy;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class WalletApiProvisioningTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBuildingDomainData;

    public function test_user_building_and_unit_wallets_are_provisioned_automatically(): void
    {
        $user = $this->createUser();
        $graph = $this->createBuildingGraph();

        $this->assertTrue(
            $user->wallets()
                ->where('currency', 'IRR')
                ->exists()
        );

        $this->assertTrue(
            $graph['building']
                ->wallets()
                ->where('currency', 'IRR')
                ->exists()
        );

        $this->assertTrue(
            $graph['unit']
                ->wallets()
                ->where('currency', 'IRR')
                ->exists()
        );
    }

    public function test_user_can_view_personal_wallet_and_its_entries(): void
    {
        $user = $this->createUser();

        Sanctum::actingAs($user);

        $wallet = app(WalletService::class)
            ->walletFor($user);

        app(WalletService::class)->credit(
            $wallet,
            250000,
            WalletTransferType::TopUp,
            'wallet-api-personal-credit',
            null,
            $user,
            'Test personal wallet credit'
        );

        $this->getJson('/api/v1/wallets/me')
            ->assertOk()
            ->assertJsonPath(
                'data.owner.type',
                'user'
            )
            ->assertJsonPath(
                'data.owner.id',
                $user->id
            )
            ->assertJsonPath(
                'data.balance',
                250000
            )
            ->assertJsonPath(
                'data.available_balance',
                250000
            );

        $this->getJson(
            "/api/v1/wallets/{$wallet->id}/entries"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.0.entry_type',
                'credit'
            )
            ->assertJsonPath(
                'data.0.amount',
                250000
            )
            ->assertJsonPath(
                'data.0.balance_after',
                250000
            )
            ->assertJsonPath(
                'data.0.transfer.type',
                'topup'
            );
    }

    public function test_resident_can_view_own_unit_wallet_but_unrelated_user_cannot(): void
    {
        $graph = $this->createBuildingGraph();

        $resident = $this->createUser();
        $outsider = $this->createUser();

        UnitOccupancy::query()->create([
            'unit_id' => $graph['unit']->id,
            'user_id' => $resident->id,
            'occupancy_type' =>
                OccupancyType::Resident,
            'starts_at' => now()->toDateString(),
            'is_primary' => true,
            'is_active' => true,
        ]);

        Sanctum::actingAs($resident);

        $this->getJson(
            "/api/v1/units/{$graph['unit']->id}/wallet"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.owner.type',
                'unit'
            )
            ->assertJsonPath(
                'data.owner.id',
                $graph['unit']->id
            );

        Sanctum::actingAs($outsider);

        $this->getJson(
            "/api/v1/units/{$graph['unit']->id}/wallet"
        )->assertForbidden();
    }

    public function test_building_wallet_requires_explicit_building_wallet_permission(): void
    {
        $graph = $this->createBuildingGraph();

        $manager = $this->createUser();

        Sanctum::actingAs($manager);

        $this->getJson(
            "/api/v1/buildings/{$graph['building']->id}/wallet"
        )->assertForbidden();

        $role = $this->createRoleWithPermission(
            'wallet-building-viewer',
            'building-wallet.view'
        );

        $this->assignRole(
            $manager,
            $role,
            $graph['building']
        );

        $this->getJson(
            "/api/v1/buildings/{$graph['building']->id}/wallet"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.owner.type',
                'building'
            )
            ->assertJsonPath(
                'data.owner.id',
                $graph['building']->id
            );
    }

    public function test_building_scoped_wallet_permission_does_not_expose_personal_wallets(): void
    {
        $graph = $this->createBuildingGraph();

        $manager = $this->createUser();
        $resident = $this->createUser();

        $role = $this->createRoleWithPermission(
            'unit-wallet-viewer',
            'wallets.view'
        );

        $this->assignRole(
            $manager,
            $role,
            $graph['building']
        );

        $residentWallet = app(WalletService::class)
            ->walletFor($resident);

        Sanctum::actingAs($manager);

        /*
         * The scoped permission is sufficient for Unit Wallets in the
         * building, but it must never grant access to a user's personal
         * Wallet because that money is not a building-owned balance.
         */
        $this->getJson(
            "/api/v1/wallets/{$residentWallet->id}/entries"
        )->assertForbidden();

        $this->getJson(
            "/api/v1/units/{$graph['unit']->id}/wallet"
        )->assertOk();
    }


    public function test_backfill_command_restores_missing_wallets_idempotently(): void
    {
        $user = $this->createUser();
        $graph = $this->createBuildingGraph();

        Wallet::query()->delete();

        $this->assertDatabaseCount(
            'wallets',
            0
        );

        $this->artisan('wallets:provision')
            ->assertExitCode(0);

        $this->assertSame(
            3,
            Wallet::query()->count()
        );

        $this->artisan('wallets:provision')
            ->assertExitCode(0);

        $this->assertSame(
            3,
            Wallet::query()->count()
        );

        $this->assertTrue(
            $user->wallets()
                ->where('currency', 'IRR')
                ->exists()
        );

        $this->assertTrue(
            $graph['building']
                ->wallets()
                ->where('currency', 'IRR')
                ->exists()
        );

        $this->assertTrue(
            $graph['unit']
                ->wallets()
                ->where('currency', 'IRR')
                ->exists()
        );
    }

    private function createRoleWithPermission(
        string $roleName,
        string $permissionName
    ): Role {
        $role = Role::query()->create([
            'name' => $roleName,
            'display_name' => $roleName,
            'is_system' => true,
        ]);

        $permission = Permission::query()->firstOrCreate(
            ['name' => $permissionName],
            [
                'display_name' => $permissionName,
                'module' => 'wallets',
            ]
        );

        $role->permissions()->syncWithoutDetaching([
            $permission->id,
        ]);

        return $role;
    }

    private function assignRole(
        User $user,
        Role $role,
        mixed $scope = null
    ): UserRoleAssignment {
        return UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' =>
                $scope?->getMorphClass(),
            'scope_id' =>
                $scope?->getKey(),
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'is_active' => true,
            'assigned_by' => null,
        ]);
    }
}
