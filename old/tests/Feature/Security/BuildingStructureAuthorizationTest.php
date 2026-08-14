<?php

namespace Tests\Feature\Security;

use App\Enums\UnitUsageType;
use App\Models\Block;
use App\Models\Building;
use App\Models\Complex;
use App\Models\Floor;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BuildingStructureAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_building_scoped_user_can_manage_only_assigned_building_structure(): void
    {
        $user = $this->createUser();

        $structureA = $this->createStructure('A');
        $structureB = $this->createStructure('B');

        $role = $this->createRoleWithPermissions(
            'building-structure-manager',
            [
                'blocks.view',
                'blocks.create',
                'blocks.update',
                'blocks.delete',

                'floors.view',
                'floors.create',
                'floors.update',
                'floors.delete',

                'units.view',
                'units.create',
                'units.update',
                'units.delete',
            ]
        );

        $this->assignRole(
            $user,
            $role,
            $structureA['building']
        );

        Sanctum::actingAs($user);

        /*
        |--------------------------------------------------------------------------
        | Blocks
        |--------------------------------------------------------------------------
        */

        $this->getJson(
            "/api/v1/buildings/{$structureA['building']->id}/blocks"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $structureA['block']->id
            );

        $this->getJson(
            "/api/v1/buildings/{$structureB['building']->id}/blocks"
        )->assertForbidden();

        $this->postJson(
            "/api/v1/buildings/{$structureA['building']->id}/blocks",
            [
                'title' => 'Block New A',
                'sort_order' => 10,
                'is_active' => true,
            ]
        )->assertCreated();

        $this->postJson(
            "/api/v1/buildings/{$structureB['building']->id}/blocks",
            [
                'title' => 'Block Denied B',
            ]
        )->assertForbidden();

        /*
        |--------------------------------------------------------------------------
        | Floors
        |--------------------------------------------------------------------------
        */

        $this->getJson(
            "/api/v1/blocks/{$structureA['block']->id}/floors"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $structureA['floor']->id
            );

        $this->getJson(
            "/api/v1/blocks/{$structureB['block']->id}/floors"
        )->assertForbidden();

        $this->postJson(
            "/api/v1/blocks/{$structureA['block']->id}/floors",
            [
                'floor_number' => 2,
                'title' => 'Second Floor',
                'sort_order' => 2,
            ]
        )->assertCreated();

        $this->postJson(
            "/api/v1/blocks/{$structureB['block']->id}/floors",
            [
                'floor_number' => 2,
                'title' => 'Denied Floor',
            ]
        )->assertForbidden();

        /*
        |--------------------------------------------------------------------------
        | Units
        |--------------------------------------------------------------------------
        */

        $this->getJson(
            "/api/v1/floors/{$structureA['floor']->id}/units"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $structureA['unit']->id
            );

        $this->getJson(
            "/api/v1/floors/{$structureB['floor']->id}/units"
        )->assertForbidden();

        $usageType = UnitUsageType::cases()[0]->value;

        $this->postJson(
            "/api/v1/floors/{$structureA['floor']->id}/units",
            [
                'unit_number' => '202',
                'title' => 'Unit 202',
                'area' => 95,
                'bedrooms' => 2,
                'usage_type' => $usageType,
                'is_active' => true,
            ]
        )->assertCreated();

        $this->postJson(
            "/api/v1/floors/{$structureB['floor']->id}/units",
            [
                'unit_number' => '999',
                'usage_type' => $usageType,
            ]
        )->assertForbidden();

        /*
        |--------------------------------------------------------------------------
        | Single Resources
        |--------------------------------------------------------------------------
        */

        $this->getJson(
            "/api/v1/units/{$structureA['unit']->id}"
        )->assertOk();

        $this->getJson(
            "/api/v1/units/{$structureB['unit']->id}"
        )->assertForbidden();

        $this->patchJson(
            "/api/v1/units/{$structureA['unit']->id}",
            [
                'title' => 'Updated Unit',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.title',
                'Updated Unit'
            );

        $this->patchJson(
            "/api/v1/units/{$structureB['unit']->id}",
            [
                'title' => 'Denied Update',
            ]
        )->assertForbidden();
    }

    public function test_parent_ids_from_payload_cannot_move_resources_to_another_parent(): void
    {
        $user = $this->createUser();

        $structureA = $this->createStructure('A');
        $structureB = $this->createStructure('B');

        $role = $this->createRoleWithPermissions(
            'structure-creator',
            [
                'blocks.create',
                'floors.create',
                'units.create',
            ]
        );

        $this->assignRole(
            $user,
            $role,
            $structureA['building']
        );

        Sanctum::actingAs($user);

        /*
         * building_id must come from route, not payload.
         */
        $blockResponse = $this->postJson(
            "/api/v1/buildings/{$structureA['building']->id}/blocks",
            [
                'building_id' => $structureB['building']->id,
                'title' => 'Protected Block',
            ]
        );

        $blockResponse->assertCreated();

        $blockId = $blockResponse->json('data.id');

        $this->assertDatabaseHas(
            'blocks',
            [
                'id' => $blockId,
                'building_id' => $structureA['building']->id,
            ]
        );

        /*
         * block_id must come from route, not payload.
         */
        $floorResponse = $this->postJson(
            "/api/v1/blocks/{$structureA['block']->id}/floors",
            [
                'block_id' => $structureB['block']->id,
                'floor_number' => 5,
                'title' => 'Protected Floor',
            ]
        );

        $floorResponse->assertCreated();

        $floorId = $floorResponse->json('data.id');

        $this->assertDatabaseHas(
            'floors',
            [
                'id' => $floorId,
                'block_id' => $structureA['block']->id,
            ]
        );

        /*
         * floor_id must come from route, not payload.
         */
        $unitResponse = $this->postJson(
            "/api/v1/floors/{$structureA['floor']->id}/units",
            [
                'floor_id' => $structureB['floor']->id,
                'unit_number' => 'PROTECTED-1',
                'usage_type' => UnitUsageType::cases()[0]->value,
            ]
        );

        $unitResponse->assertCreated();

        $unitId = $unitResponse->json('data.id');

        $this->assertDatabaseHas(
            'units',
            [
                'id' => $unitId,
                'floor_id' => $structureA['floor']->id,
            ]
        );
    }

    public function test_unique_structure_constraints_are_validated_per_parent(): void
    {
        $user = $this->createUser();

        $structure = $this->createStructure('A');

        $role = $this->createRoleWithPermissions(
            'structure-validation-manager',
            [
                'blocks.create',
                'floors.create',
                'units.create',
            ]
        );

        $this->assignRole(
            $user,
            $role,
            $structure['building']
        );

        Sanctum::actingAs($user);

        $this->postJson(
            "/api/v1/buildings/{$structure['building']->id}/blocks",
            [
                'title' => $structure['block']->title,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');

        $this->postJson(
            "/api/v1/blocks/{$structure['block']->id}/floors",
            [
                'floor_number' => $structure['floor']->floor_number,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('floor_number');

        $this->postJson(
            "/api/v1/floors/{$structure['floor']->id}/units",
            [
                'unit_number' => $structure['unit']->unit_number,
                'usage_type' => UnitUsageType::cases()[0]->value,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('unit_number');
    }

    private function createUser(): User
    {
        return User::query()->create([
            'first_name' => 'Structure',
            'last_name' => 'Manager',
            'mobile' => '09120000001',
            'email' => 'structure.manager@example.test',
            'mobile_verified_at' => now(),
            'email_verified_at' => now(),
            'password' => 'TestPassword123!',
            'is_active' => true,
            'is_blocked' => false,
        ]);
    }

    private function createStructure(string $suffix): array
    {
        $complex = Complex::query()->create([
            'code' => "CMP-{$suffix}",
            'title' => "Complex {$suffix}",
            'province' => 'Tehran',
            'city' => 'Tehran',
            'is_active' => true,
        ]);

        $building = Building::query()->create([
            'complex_id' => $complex->id,
            'code' => "BLD-{$suffix}",
            'title' => "Building {$suffix}",
            'is_active' => true,
        ]);

        $block = Block::query()->create([
            'building_id' => $building->id,
            'title' => "Block {$suffix}",
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $floor = Floor::query()->create([
            'block_id' => $block->id,
            'floor_number' => 1,
            'title' => "Floor {$suffix}",
            'sort_order' => 1,
        ]);

        $unit = Unit::query()->create([
            'floor_id' => $floor->id,
            'unit_number' => "101-{$suffix}",
            'title' => "Unit {$suffix}",
            'area' => 100,
            'bedrooms' => 2,
            'usage_type' => UnitUsageType::cases()[0]->value,
            'is_active' => true,
        ]);

        return compact(
            'complex',
            'building',
            'block',
            'floor',
            'unit'
        );
    }

    private function createRoleWithPermissions(
        string $name,
        array $permissionNames
    ): Role {
        $role = Role::query()->create([
            'name' => $name,
            'display_name' => $name,
            'is_system' => true,
        ]);

        foreach ($permissionNames as $permissionName) {
            $module = explode('.', $permissionName)[0];

            $permission = Permission::query()->firstOrCreate(
                [
                    'name' => $permissionName,
                ],
                [
                    'display_name' => $permissionName,
                    'module' => $module,
                ]
            );

            $role->permissions()->syncWithoutDetaching([
                $permission->id,
            ]);
        }

        return $role;
    }

    private function assignRole(
        User $user,
        Role $role,
        mixed $scope
    ): UserRoleAssignment {
        return UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,

            'scope_type' => $scope->getMorphClass(),
            'scope_id' => $scope->getKey(),

            'starts_at' => now()->subDay(),
            'ends_at' => null,

            'is_active' => true,
            'assigned_by' => null,
        ]);
    }
}
