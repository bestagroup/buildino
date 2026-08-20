<?php

namespace Tests\Feature\Authorization;

use App\Models\Building;
use App\Models\Complex;
use App\Models\Floor;
use App\Models\InvoiceItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\UnitInvoice;
use App\Models\UnitOccupancy;
use App\Models\UnitOwnership;
use App\Models\User;
use App\Services\Web\ManagementDashboardAccessService;
use App\Support\Authorization\PermissionChecker;
use Database\Seeders\AccessScenarioSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleMatrixAccessScenarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_database_seeder_synchronizes_full_role_matrix(): void
    {
        $this->seed(
            DatabaseSeeder::class
        );

        $this->assertEqualsCanonicalizing(
            array_keys(
                config(
                    'role_matrix.roles',
                    []
                )
            ),
            Role::query()
                ->pluck('name')
                ->all()
        );
    }

    public function test_role_matrix_references_only_registered_permissions(): void
    {
        $this->seed(
            RoleMatrixSeeder::class
        );

        $matrix =
            config(
                'role_matrix.roles',
                []
            );

        $this->assertCount(
            9,
            $matrix
        );

        foreach (
            $matrix
            as $name => $configuration
        ) {
            $role =
                Role::query()
                    ->where(
                        'name',
                        $name
                    )
                    ->first();

            $this->assertNotNull(
                $role,
                "Role [{$name}] was not created."
            );

            $expected =
                collect(
                    $configuration[
                        'permissions'
                    ] ?? []
                );

            if (
                $expected
                    ->contains('*')
            ) {
                $this->assertSame(
                    Permission::query()
                        ->count(),
                    $role
                        ->permissions()
                        ->count()
                );

                continue;
            }

            $this->assertEqualsCanonicalizing(
                $expected
                    ->all(),
                $role
                    ->permissions()
                    ->pluck(
                        'permissions.name'
                    )
                    ->all(),
                "Role [{$name}] permission matrix differs from config."
            );
        }
    }

    public function test_complex_manager_sees_only_buildings_inside_assigned_complex(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $manager =
            $this->user(
                'role.complex@buildino.local'
            );

        Sanctum::actingAs(
            $manager
        );

        $response =
            $this->getJson(
                '/api/v1/buildings?per_page=100'
            );

        $response->assertOk();

        $titles =
            collect(
                $response->json(
                    'data',
                    []
                )
            )->pluck(
                'title'
            );

        $this->assertTrue(
            $titles->contains(
                'ساختمان آلفا'
            )
        );

        $this->assertTrue(
            $titles->contains(
                'ساختمان بتا'
            )
        );

        $this->assertFalse(
            $titles->contains(
                'ساختمان گاما - خارج از محدوده'
            )
        );
    }

    public function test_complex_manager_complex_list_is_scope_filtered(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $manager =
            $this->user(
                'role.complex@buildino.local'
            );

        Sanctum::actingAs(
            $manager
        );

        $response =
            $this->getJson(
                '/api/v1/complexes?per_page=100'
            );

        $response->assertOk();

        $codes =
            collect(
                $response->json(
                    'data',
                    []
                )
            )
                ->pluck('code')
                ->values()
                ->all();

        $this->assertSame(
            [
                'DEMO-COMPLEX-A',
            ],
            $codes
        );
    }

    public function test_building_manager_is_isolated_to_exact_building(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $manager =
            $this->user(
                'role.building@buildino.local'
            );

        Sanctum::actingAs(
            $manager
        );

        $response =
            $this->getJson(
                '/api/v1/buildings?per_page=100'
            );

        $response->assertOk();

        $titles =
            collect(
                $response->json(
                    'data',
                    []
                )
            )->pluck(
                'title'
            )
                ->values()
                ->all();

        $this->assertSame(
            [
                'ساختمان آلفا',
            ],
            $titles
        );

        $buildingA =
            Building::query()
                ->where(
                    'code',
                    'DEMO-BUILDING-A'
                )
                ->firstOrFail();

        $buildingB =
            Building::query()
                ->where(
                    'code',
                    'DEMO-BUILDING-B'
                )
                ->firstOrFail();

        $permissions =
            app(
                PermissionChecker::class
            );

        $this->assertTrue(
            $permissions->allows(
                $manager,
                'units.view',
                $buildingA
            )
        );

        $this->assertFalse(
            $permissions->allows(
                $manager,
                'units.view',
                $buildingB
            )
        );
    }

    public function test_building_manager_can_manage_only_units_of_assigned_building(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $manager =
            $this->user(
                'role.building@buildino.local'
            );

        $assignedFloor =
            Floor::query()
                ->whereHas(
                    'block.building',
                    fn ($query) =>
                        $query->where(
                            'code',
                            'DEMO-BUILDING-A'
                        )
                )
                ->firstOrFail();

        $outsideFloor =
            Floor::query()
                ->whereHas(
                    'block.building',
                    fn ($query) =>
                        $query->where(
                            'code',
                            'DEMO-BUILDING-B'
                        )
                )
                ->firstOrFail();

        Sanctum::actingAs(
            $manager
        );

        $this->getJson(
            "/api/v1/floors/{$assignedFloor->getKey()}/units"
        )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data'
            );

        $this->postJson(
            "/api/v1/floors/{$assignedFloor->getKey()}/units",
            [
                'unit_number' =>
                    '103',

                'title' =>
                    'واحد 103 مدیر ساختمان',

                'area' =>
                    90,

                'bedrooms' =>
                    2,

                'usage_type' =>
                    'residential',

                'is_active' =>
                    true,
            ]
        )->assertCreated();

        $this->getJson(
            "/api/v1/floors/{$outsideFloor->getKey()}/units"
        )->assertForbidden();

        $this->postJson(
            "/api/v1/floors/{$outsideFloor->getKey()}/units",
            [
                'unit_number' =>
                    '103',

                'usage_type' =>
                    'residential',
            ]
        )->assertForbidden();
    }

    public function test_management_dashboard_scope_matches_role_assignment(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $access =
            app(
                ManagementDashboardAccessService::class
            );

        $complexManager =
            $this->user(
                'role.complex@buildino.local'
            );

        $buildingManager =
            $this->user(
                'role.building@buildino.local'
            );

        $complexCodes =
            $access
                ->accessibleBuildings(
                    $complexManager
                )
                ->pluck('code')
                ->sort()
                ->values()
                ->all();

        $this->assertSame(
            [
                'DEMO-BUILDING-A',
                'DEMO-BUILDING-B',
            ],
            $complexCodes
        );

        $this->assertSame(
            [
                'DEMO-BUILDING-A',
            ],
            $access
                ->accessibleBuildings(
                    $buildingManager
                )
                ->pluck('code')
                ->values()
                ->all()
        );
    }

    public function test_building_manager_cannot_open_global_user_management(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $manager =
            $this->user(
                'role.building@buildino.local'
            );

        $this->actingAs(
            $manager,
            'web'
        );

        $this->get(
            '/management/operations/users'
        )->assertForbidden();

        $this->get(
            '/management'
        )
            ->assertOk()
            ->assertSee(
                'ساختمان آلفا'
            )
            ->assertDontSee(
                'ساختمان بتا'
            )
            ->assertDontSee(
                'کاربران و دسترسی'
            );
    }

    public function test_owner_and_tenant_are_relation_driven_and_do_not_get_management_dashboard_access(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $owner =
            $this->user(
                'role.owner@buildino.local'
            );

        $tenant =
            $this->user(
                'role.tenant@buildino.local'
            );

        $this->assertTrue(
            UnitOwnership::query()
                ->where(
                    'user_id',
                    $owner->getKey()
                )
                ->where(
                    'is_active',
                    true
                )
                ->exists()
        );

        $this->assertTrue(
            UnitOccupancy::query()
                ->where(
                    'user_id',
                    $tenant->getKey()
                )
                ->where(
                    'is_active',
                    true
                )
                ->exists()
        );

        $access =
            app(
                ManagementDashboardAccessService::class
            );

        $this->assertFalse(
            $access->hasAnyAccess(
                $owner
            )
        );

        $this->assertFalse(
            $access->hasAnyAccess(
                $tenant
            )
        );

        $this->post(
            '/management/login',
            [
                'login' =>
                    $owner->mobile,

                'password' =>
                    'Demo@1405',
            ]
        )
            ->assertSessionHasErrors(
                'login'
            );

        $this->assertGuest(
            'web'
        );
    }

    public function test_access_scenario_is_idempotent_for_fixed_demo_entities(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $this->seed(
            AccessScenarioSeeder::class
        );

        $this->assertSame(
            9,
            User::query()
                ->where(
                    'email',
                    'like',
                    'role.%@buildino.local'
                )
                ->count()
        );

        $this->assertSame(
            3,
            Building::query()
                ->where(
                    'code',
                    'like',
                    'DEMO-BUILDING-%'
                )
                ->count()
        );

        $this->assertSame(
            2,
            Complex::query()
                ->where(
                    'code',
                    'like',
                    'DEMO-COMPLEX-%'
                )
                ->count()
        );

        $this->assertSame(
            2,
            UnitInvoice::query()
                ->whereIn(
                    'invoice_number',
                    [
                        'ACCESS-OWNER-CHARGE',
                        'ACCESS-TENANT-CHARGE',
                    ]
                )
                ->count()
        );

        $this->assertSame(
            2,
            InvoiceItem::query()
                ->whereHas(
                    'unitInvoice',
                    fn ($query) =>
                        $query->whereIn(
                            'invoice_number',
                            [
                                'ACCESS-OWNER-CHARGE',
                                'ACCESS-TENANT-CHARGE',
                            ]
                        )
                )
                ->count()
        );

        $this->assertSame(
            2,
            ServiceRequest::query()
                ->whereIn(
                    'request_number',
                    [
                        'ACCESS-PROVIDER-SERVICE',
                        'ACCESS-OTHER-SERVICE',
                    ]
                )
                ->count()
        );
    }

    private function user(
        string $email
    ): User {
        return User::query()
            ->where(
                'email',
                $email
            )
            ->firstOrFail();
    }
}
