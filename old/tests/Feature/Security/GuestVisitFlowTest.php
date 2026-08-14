<?php

namespace Tests\Feature\Security;

use App\Enums\GuestAccessAction;
use App\Enums\GuestVisitStatus;
use App\Enums\OccupancyType;
use App\Enums\UnitUsageType;
use App\Models\Block;
use App\Models\Building;
use App\Models\Complex;
use App\Models\Floor;
use App\Models\GuestVisit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitOccupancy;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuestVisitFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_can_register_and_view_guests_only_for_own_unit(): void
    {
        $resident = $this->createUser(
            '09122220001',
            'resident-guest@example.test'
        );

        $structureA = $this->createStructure('RES-A');
        $structureB = $this->createStructure('RES-B');

        UnitOccupancy::query()->create([
            'unit_id' => $structureA['unit']->id,
            'user_id' => $resident->id,
            'occupancy_type' => OccupancyType::Resident->value,
            'starts_at' => now()->toDateString(),
            'is_primary' => true,
            'is_active' => true,
        ]);

        Sanctum::actingAs($resident);

        $response = $this->postJson(
            "/api/v1/units/{$structureA['unit']->id}/guest-visits",
            [
                'guest' => [
                    'first_name' => 'Ali',
                    'last_name' => 'Guest',
                    'mobile' => '09123334444',
                    'national_code' => '0012345678',
                    'vehicle_plate' => '11A111',
                ],

                'expected_entry_at' => now()
                    ->addHour()
                    ->toDateTimeString(),

                'expected_exit_at' => now()
                    ->addHours(3)
                    ->toDateTimeString(),

                'description' => 'Resident guest',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.unit_id',
                $structureA['unit']->id
            )
            ->assertJsonPath(
                'data.status',
                GuestVisitStatus::Invited->value
            );

        $this->getJson(
            "/api/v1/units/{$structureA['unit']->id}/guest-visits"
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            );

        $this->getJson(
            "/api/v1/units/{$structureB['unit']->id}/guest-visits"
        )->assertForbidden();

        $this->postJson(
            "/api/v1/units/{$structureB['unit']->id}/guest-visits",
            [
                'guest' => [
                    'first_name' => 'Denied',
                    'last_name' => 'Guest',
                ],
            ]
        )->assertForbidden();
    }

    public function test_scoped_manager_can_manage_visits_only_inside_assigned_building(): void
    {
        $manager = $this->createUser(
            '09122221001',
            'guest-manager@example.test'
        );

        $structureA = $this->createStructure('MGR-A');
        $structureB = $this->createStructure('MGR-B');

        $role = $this->createRoleWithPermissions(
            'guest-manager',
            [
                'guest-visits.view',
                'guest-visits.create',
                'guest-visits.update',
            ]
        );

        $this->assignRole(
            $manager,
            $role,
            $structureA['building']
        );

        Sanctum::actingAs($manager);

        $this->postJson(
            "/api/v1/units/{$structureA['unit']->id}/guest-visits",
            [
                'guest' => [
                    'first_name' => 'Allowed',
                    'last_name' => 'Guest',
                ],
            ]
        )->assertCreated();

        $this->postJson(
            "/api/v1/units/{$structureB['unit']->id}/guest-visits",
            [
                'guest' => [
                    'first_name' => 'Denied',
                    'last_name' => 'Guest',
                ],
            ]
        )->assertForbidden();
    }

    public function test_resident_cannot_record_physical_entry_or_exit_without_explicit_permission(): void
    {
        $resident = $this->createUser(
            '09122222001',
            'resident-gate@example.test'
        );

        $structure = $this->createStructure('GATE-RES');

        UnitOccupancy::query()->create([
            'unit_id' => $structure['unit']->id,
            'user_id' => $resident->id,
            'occupancy_type' => OccupancyType::Resident->value,
            'starts_at' => now()->toDateString(),
            'is_primary' => true,
            'is_active' => true,
        ]);

        Sanctum::actingAs($resident);

        $visit = $this->postJson(
            "/api/v1/units/{$structure['unit']->id}/guest-visits",
            [
                'guest' => [
                    'first_name' => 'Gate',
                    'last_name' => 'Guest',
                ],
            ]
        )
            ->assertCreated()
            ->json('data.id');

        $this->postJson(
            "/api/v1/guest-visits/{$visit}/entry",
            [
                'gate' => 'A',
                'entry_method' => 'manual',
            ]
        )->assertForbidden();
    }

    public function test_security_operator_can_record_entry_then_exit_and_logs_are_immutable_history(): void
    {
        $registrar = $this->createUser(
            '09122223001',
            'registrar@example.test'
        );

        $security = $this->createUser(
            '09122223002',
            'security@example.test'
        );

        $structure = $this->createStructure('ACCESS');

        UnitOccupancy::query()->create([
            'unit_id' => $structure['unit']->id,
            'user_id' => $registrar->id,
            'occupancy_type' => OccupancyType::Resident->value,
            'starts_at' => now()->toDateString(),
            'is_primary' => true,
            'is_active' => true,
        ]);

        $securityRole = $this->createRoleWithPermissions(
            'security-operator',
            [
                'guest-visits.view',
                'guest-visits.update',
            ]
        );

        $this->assignRole(
            $security,
            $securityRole,
            $structure['building']
        );

        Sanctum::actingAs($registrar);

        $visitId = $this->postJson(
            "/api/v1/units/{$structure['unit']->id}/guest-visits",
            [
                'guest' => [
                    'first_name' => 'Access',
                    'last_name' => 'Guest',
                    'vehicle_plate' => '22B222',
                ],
            ]
        )
            ->assertCreated()
            ->json('data.id');

        Sanctum::actingAs($security);

        $entry = $this->postJson(
            "/api/v1/guest-visits/{$visitId}/entry",
            [
                'gate' => 'North Gate',
                'entry_method' => 'manual',
                'notes' => 'Identity checked',
            ]
        );

        $entry
            ->assertOk()
            ->assertJsonPath(
                'data.action',
                GuestAccessAction::Entry->value
            )
            ->assertJsonPath(
                'data.verified_by',
                $security->id
            )
            ->assertJsonPath(
                'data.vehicle_plate',
                '22B222'
            );

        $this->assertDatabaseHas(
            'guest_visits',
            [
                'id' => $visitId,
                'status' => GuestVisitStatus::Entered->value,
            ]
        );

        $exit = $this->postJson(
            "/api/v1/guest-visits/{$visitId}/exit",
            [
                'gate' => 'North Gate',
                'entry_method' => 'manual',
            ]
        );

        $exit
            ->assertOk()
            ->assertJsonPath(
                'data.action',
                GuestAccessAction::Exit->value
            );

        $this->assertDatabaseHas(
            'guest_visits',
            [
                'id' => $visitId,
                'status' => GuestVisitStatus::Exited->value,
            ]
        );

        $this->getJson(
            "/api/v1/guest-visits/{$visitId}/access-logs"
        )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data'
            );

        $this->assertDatabaseCount(
            'guest_access_logs',
            2
        );
    }

    public function test_exit_before_entry_and_duplicate_entry_are_rejected(): void
    {
        $security = $this->createUser(
            '09122224001',
            'security-transition@example.test'
        );

        $structure = $this->createStructure('TRANS');

        $role = $this->createRoleWithPermissions(
            'transition-security',
            [
                'guest-visits.update',
            ]
        );

        $this->assignRole(
            $security,
            $role,
            $structure['building']
        );

        $visit = GuestVisit::query()->create([
            'guest_id' => \App\Models\Guest::query()->create([
                'first_name' => 'Transition',
                'last_name' => 'Guest',
            ])->id,
            'unit_id' => $structure['unit']->id,
            'registered_by' => $security->id,
            'status' => GuestVisitStatus::Invited->value,
        ]);

        Sanctum::actingAs($security);

        $this->postJson(
            "/api/v1/guest-visits/{$visit->id}/exit"
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'visit'
            );

        $this->postJson(
            "/api/v1/guest-visits/{$visit->id}/entry"
        )->assertOk();

        $this->postJson(
            "/api/v1/guest-visits/{$visit->id}/entry"
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'visit'
            );

        $this->assertDatabaseCount(
            'guest_access_logs',
            1
        );
    }

    public function test_cancelled_visit_cannot_enter(): void
    {
        $resident = $this->createUser(
            '09122225001',
            'resident-cancel@example.test'
        );

        $security = $this->createUser(
            '09122225002',
            'security-cancel@example.test'
        );

        $structure = $this->createStructure('CANCEL');

        UnitOccupancy::query()->create([
            'unit_id' => $structure['unit']->id,
            'user_id' => $resident->id,
            'occupancy_type' => OccupancyType::Resident->value,
            'starts_at' => now()->toDateString(),
            'is_primary' => true,
            'is_active' => true,
        ]);

        $role = $this->createRoleWithPermissions(
            'cancel-security',
            [
                'guest-visits.update',
            ]
        );

        $this->assignRole(
            $security,
            $role,
            $structure['building']
        );

        Sanctum::actingAs($resident);

        $visitId = $this->postJson(
            "/api/v1/units/{$structure['unit']->id}/guest-visits",
            [
                'guest' => [
                    'first_name' => 'Cancelled',
                    'last_name' => 'Guest',
                ],
            ]
        )
            ->assertCreated()
            ->json('data.id');

        $this->postJson(
            "/api/v1/guest-visits/{$visitId}/cancel"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                GuestVisitStatus::Cancelled->value
            );

        Sanctum::actingAs($security);

        $this->postJson(
            "/api/v1/guest-visits/{$visitId}/entry"
        )->assertUnprocessable();

        $this->assertDatabaseCount(
            'guest_access_logs',
            0
        );
    }

    public function test_expiration_command_expires_only_invited_visits_past_expected_exit(): void
    {
        $user = $this->createUser(
            '09122226001',
            'expire-guest@example.test'
        );

        $structure = $this->createStructure('EXP-G');

        $guest = \App\Models\Guest::query()->create([
            'first_name' => 'Expired',
            'last_name' => 'Guest',
        ]);

        $visit = GuestVisit::query()->create([
            'guest_id' => $guest->id,
            'unit_id' => $structure['unit']->id,
            'registered_by' => $user->id,
            'expected_exit_at' => now()->subMinute(),
            'status' => GuestVisitStatus::Invited->value,
        ]);

        Artisan::call(
            'domain:expire-guest-visits'
        );

        $this->assertDatabaseHas(
            'guest_visits',
            [
                'id' => $visit->id,
                'status' => GuestVisitStatus::Expired->value,
            ]
        );
    }

    private function createUser(
        string $mobile,
        string $email
    ): User {
        return User::query()->create([
            'first_name' => 'Guest',
            'last_name' => 'Tester',
            'mobile' => $mobile,
            'email' => $email,
            'mobile_verified_at' => now(),
            'email_verified_at' => now(),
            'password' => 'TestPassword123!',
            'is_active' => true,
            'is_blocked' => false,
        ]);
    }

    private function createStructure(
        string $suffix
    ): array {
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
            $module = explode(
                '.',
                $permissionName
            )[0];

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
