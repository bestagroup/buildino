<?php

namespace Tests\Feature\Security;

use App\Models\Block;
use App\Models\Building;
use App\Models\Complex;
use App\Models\Floor;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitOccupancy;
use App\Models\UnitOwnership;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\Security\BuildingAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildingScopedAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private BuildingAccessService $access;

    protected function setUp(): void
    {
        parent::setUp();

        $this->access = app(
            BuildingAccessService::class
        );
    }

    public function test_global_role_can_access_any_building(): void
    {
        $user = User::factory()->create();

        $role = Role::query()->create([
            'name' => 'admin',
            'display_name' => 'Administrator',
            'is_system' => true,
        ]);

        UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,

            'scope_type' => null,
            'scope_id' => null,

            'starts_at' => now()->subDay(),
            'ends_at' => null,

            'is_active' => true,
        ]);

        $buildingA = Building::factory()->create();
        $buildingB = Building::factory()->create();

        $this->assertTrue(
            $this->access->allows(
                $user,
                $buildingA
            )
        );

        $this->assertTrue(
            $this->access->allows(
                $user,
                $buildingB
            )
        );
    }

    public function test_complex_scoped_role_can_access_only_buildings_of_same_complex(): void
    {
        $user = User::factory()->create();

        $role = Role::query()->create([
            'name' => 'manager',
            'display_name' => 'Manager',
            'is_system' => true,
        ]);

        $complexA = Complex::factory()->create();
        $complexB = Complex::factory()->create();

        $buildingA1 = Building::factory()
            ->for($complexA)
            ->create();

        $buildingA2 = Building::factory()
            ->for($complexA)
            ->create();

        $buildingB = Building::factory()
            ->for($complexB)
            ->create();

        UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,

            'scope_type' => $complexA->getMorphClass(),
            'scope_id' => $complexA->id,

            'starts_at' => now()->subDay(),
            'ends_at' => null,

            'is_active' => true,
        ]);

        $this->assertTrue(
            $this->access->allows(
                $user,
                $buildingA1
            )
        );

        $this->assertTrue(
            $this->access->allows(
                $user,
                $buildingA2
            )
        );

        $this->assertFalse(
            $this->access->allows(
                $user,
                $buildingB
            )
        );
    }

    public function test_building_scoped_role_cannot_access_another_building(): void
    {
        $user = User::factory()->create();

        $role = Role::query()->create([
            'name' => 'manager',
            'display_name' => 'Manager',
            'is_system' => true,
        ]);

        $buildingA = Building::factory()->create();
        $buildingB = Building::factory()->create();

        UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,

            'scope_type' => $buildingA->getMorphClass(),
            'scope_id' => $buildingA->id,

            'starts_at' => now()->subDay(),
            'ends_at' => null,

            'is_active' => true,
        ]);

        $this->assertTrue(
            $this->access->allows(
                $user,
                $buildingA
            )
        );

        $this->assertFalse(
            $this->access->allows(
                $user,
                $buildingB
            )
        );
    }

    public function test_expired_assignment_does_not_grant_access(): void
    {
        $user = User::factory()->create();

        $role = Role::query()->create([
            'name' => 'manager',
            'display_name' => 'Manager',
            'is_system' => true,
        ]);

        $building = Building::factory()->create();

        UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,

            'scope_type' => $building->getMorphClass(),
            'scope_id' => $building->id,

            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),

            'is_active' => true,
        ]);

        $this->assertFalse(
            $this->access->allows(
                $user,
                $building
            )
        );
    }

    public function test_inactive_assignment_does_not_grant_access(): void
    {
        $user = User::factory()->create();

        $role = Role::query()->create([
            'name' => 'manager',
            'display_name' => 'Manager',
            'is_system' => true,
        ]);

        $building = Building::factory()->create();

        UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,

            'scope_type' => $building->getMorphClass(),
            'scope_id' => $building->id,

            'starts_at' => now()->subDay(),
            'ends_at' => null,

            'is_active' => false,
        ]);

        $this->assertFalse(
            $this->access->allows(
                $user,
                $building
            )
        );
    }

    public function test_blocked_user_is_denied_even_with_active_assignment(): void
    {
        $user = User::factory()->create([
            'is_blocked' => true,
        ]);

        $role = Role::query()->create([
            'name' => 'manager',
            'display_name' => 'Manager',
            'is_system' => true,
        ]);

        $building = Building::factory()->create();

        UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,

            'scope_type' => $building->getMorphClass(),
            'scope_id' => $building->id,

            'is_active' => true,
        ]);

        $this->assertFalse(
            $this->access->allows(
                $user,
                $building
            )
        );
    }

    public function test_owner_has_access_to_building_containing_owned_unit(): void
    {
        $owner = User::factory()->create();

        [$building, $unit] = $this->createUnitTree();

        UnitOwnership::query()->create([
            'unit_id' => $unit->id,
            'user_id' => $owner->id,

            'ownership_percentage' => 100,

            'starts_at' => today()->subYear(),
            'ends_at' => null,

            'is_primary' => true,
            'is_active' => true,

            'created_by' => $owner->id,
        ]);

        $this->assertTrue(
            $this->access->allows(
                $owner,
                $building
            )
        );

        $otherBuilding = Building::factory()->create();

        $this->assertFalse(
            $this->access->allows(
                $owner,
                $otherBuilding
            )
        );
    }

    public function test_current_occupant_has_access_to_building(): void
    {
        $tenant = User::factory()->create();

        [$building, $unit] = $this->createUnitTree();

        UnitOccupancy::query()->create([
            'unit_id' => $unit->id,
            'user_id' => $tenant->id,

            'occupancy_type' => 'tenant',

            'starts_at' => today()->subMonth(),
            'ends_at' => null,

            'is_primary' => true,
            'is_active' => true,

            'created_by' => $tenant->id,
        ]);

        $this->assertTrue(
            $this->access->allows(
                $tenant,
                $building
            )
        );

        $otherBuilding = Building::factory()->create();

        $this->assertFalse(
            $this->access->allows(
                $tenant,
                $otherBuilding
            )
        );
    }

    /**
     * @return array{0: Building, 1: Unit}
     */
    private function createUnitTree(): array
    {
        $building = Building::factory()->create();

        $block = Block::factory()
            ->for($building)
            ->create();

        $floor = Floor::factory()
            ->for($block)
            ->create();

        $unit = Unit::factory()
            ->for($floor)
            ->create();

        return [
            $building,
            $unit,
        ];
    }
}
