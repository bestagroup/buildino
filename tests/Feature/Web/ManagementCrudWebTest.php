<?php

namespace Tests\Feature\Web;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManagementCrudWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_operations_center(): void
    {
        $this->get(
            '/management/operations'
        )->assertRedirect(
            '/management/login'
        );
    }

    public function test_global_manager_can_view_operations_catalog_and_complex_form_page(): void
    {
        $user =
            $this->createManagementUser();

        $this->actingAs(
            $user,
            'web'
        );

        $this->get(
            '/management/operations'
        )
            ->assertOk()
            ->assertSee(
                'مرکز عملیات Buildino'
            )
            ->assertSee(
                'مجتمع‌ها'
            )
            ->assertSee(
                'کاربران'
            )
            ->assertDontSee(
                'تیکت‌های پشتیبانی'
            );

        $this->get(
            '/management/operations/complexes'
        )
            ->assertOk()
            ->assertSee(
                'مجتمع‌ها'
            )
            ->assertSee(
                'ثبت رکورد جدید'
            );

        $this->get(
            '/management/operations/buildings'
        )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component(
                        'management/operations/Resource'
                    )
                    ->where(
                        'resourceKey',
                        'buildings'
                    )
                    ->where(
                        'resource.list.url',
                        '/api/v1/buildings?per_page=100'
                    )
                    ->where(
                        'resource.fields.0.lookup',
                        'complexes'
                    )
            );
    }

    public function test_authenticated_management_session_can_call_same_origin_protected_api(): void
    {
        $manager =
            $this->createManagementUser();

        $this->actingAs(
            $manager,
            'web'
        );

        $this->withHeaders([
            'Origin' => config(
                'app.url',
                'http://localhost'
            ),
            'Referer' => rtrim(
                config(
                    'app.url',
                    'http://localhost'
                ),
                '/'
            )
                .'/management/operations/complexes',
        ])
            ->getJson(
                '/api/v1/complexes'
            )
            ->assertOk();
    }

    public function test_user_crud_endpoint_creates_updates_and_soft_deletes_user(): void
    {
        $manager =
            $this->createManagementUser();

        $this->actingAs(
            $manager,
            'web'
        );

        $create =
            $this->postJson(
                '/management/data/users',
                [
                    'first_name' => 'کاربر',
                    'last_name' => 'آزمایشی',
                    'national_code' => '0012345678',
                    'mobile' => '09121112233',
                    'email' => 'crud-user@buildino.local',
                    'password' => 'Password@123',
                    'verify_mobile' => true,
                    'is_active' => true,
                    'is_blocked' => false,
                ]
            );

        $create
            ->assertCreated()
            ->assertJsonPath(
                'data.mobile',
                '09121112233'
            );

        $userId =
            (int) $create
                ->json(
                    'data.id'
                );

        $this->patchJson(
            "/management/data/users/{$userId}",
            [
                'first_name' => 'ویرایش',
                'is_blocked' => true,
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.first_name',
                'ویرایش'
            )
            ->assertJsonPath(
                'data.is_blocked',
                true
            );

        $this->deleteJson(
            "/management/data/users/{$userId}"
        )
            ->assertOk();

        $this->assertSoftDeleted(
            'users',
            [
                'id' => $userId,
            ]
        );
    }

    public function test_all_configured_http_operations_match_a_registered_route(): void
    {
        $resources =
            config(
                'management_crud.resources',
                []
            );

        $this->assertGreaterThanOrEqual(
            30,
            count($resources)
        );

        foreach (
            $resources as $resourceKey => $resource
        ) {
            foreach (
                [
                    'list',
                    'show',
                    'create',
                    'update',
                    'delete',
                ] as $operationKey
            ) {
                $operation =
                    $resource[
                        $operationKey
                    ] ?? null;

                if (! is_array($operation)) {
                    continue;
                }

                $this->assertOperationRouteExists(
                    $resourceKey,
                    $operationKey,
                    $operation
                );
            }

            foreach (
                $resource['actions'] ?? [] as $action
            ) {
                $this->assertOperationRouteExists(
                    $resourceKey,
                    'action:'
                    .(
                        $action['key']
                        ?? 'unknown'
                    ),
                    $action
                );
            }
        }
    }

    public function test_charge_formula_form_uses_guided_builder_instead_of_raw_json(): void
    {
        $fields = collect(
            config(
                'management_crud.resources.charge-formulas.fields',
                []
            )
        );

        $this->assertSame(
            'charge_formula_builder',
            $fields->firstWhere('name', 'builder')['type'] ?? null
        );
        $this->assertFalse(
            $fields->contains('name', 'configuration')
        );
        $this->assertFalse(
            $fields->contains('name', 'items')
        );

        $script = (string) file_get_contents(
            public_path('js/buildino-crud.js')
        );

        $this->assertStringContainsString(
            'createChargeFormulaBuilder',
            $script
        );
        $this->assertStringContainsString(
            'data-formula-expression',
            $script
        );
    }

    public function test_crud_drawer_uses_non_blocking_loading_and_accessible_dialog_contract(): void
    {
        $user =
            $this->createManagementUser();

        $this->actingAs(
            $user,
            'web'
        );

        $this->get(
            '/management/operations/complexes'
        )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component(
                        'management/operations/Resource'
                    )
                    ->where(
                        'resourceKey',
                        'complexes'
                    )
                    ->where(
                        'resource.create.url',
                        '/api/v1/complexes'
                    )
                    ->where(
                        'resource.update.url',
                        '/api/v1/complexes/{id}'
                    )
                    ->where(
                        'resource.delete.url',
                        '/api/v1/complexes/{id}'
                    )
            );

        $this->get(
            '/management/operations/users'
        )
            ->assertOk()
            ->assertSee(
                'role="dialog"',
                false
            )
            ->assertSee(
                'aria-modal="true"',
                false
            )
            ->assertSee(
                'aria-labelledby="crudDrawerTitle"',
                false
            )
            ->assertSee(
                'aria-busy="false"',
                false
            );

        $drawer = (string) file_get_contents(
            resource_path(
                'js/components/management/UiDrawer.vue'
            )
        );

        $this->assertStringContainsString(
            'role="dialog"',
            $drawer
        );
        $this->assertStringContainsString(
            'aria-modal="true"',
            $drawer
        );
        $this->assertStringContainsString(
            'focusableSelector',
            $drawer
        );

        $script = (string) file_get_contents(
            public_path('js/buildino-crud.js')
        );

        $this->assertStringContainsString(
            'renderDrawerSkeleton();',
            $script
        );
        $this->assertStringContainsString(
            'await nextPaint();',
            $script
        );
        $this->assertStringContainsString(
            'const lookupCache = new Map();',
            $script
        );
        $this->assertStringContainsString(
            'await Promise.all(',
            $script
        );
    }

    public function test_invitation_form_uses_scoped_multi_user_selection_and_custom_message(): void
    {
        $fields = collect(
            config(
                'management_crud.resources.invitations.fields',
                []
            )
        );
        $recipients = $fields->firstWhere('name', 'user_ids');
        $message = $fields->firstWhere('name', 'message');

        $this->assertSame('multiselect', $recipients['type'] ?? null);
        $this->assertSame(
            'invitable_users',
            $recipients['lookup'] ?? null
        );
        $this->assertTrue($recipients['required'] ?? false);
        $this->assertSame('textarea', $message['type'] ?? null);
        $this->assertTrue($message['required'] ?? false);
        $this->assertFalse($fields->contains('name', 'mobile'));
        $this->assertFalse($fields->contains('name', 'email'));
    }

    public function test_facility_schedule_create_form_uses_multi_day_selection(): void
    {
        $fields = collect(
            config(
                'management_crud.resources.facility-schedules.fields',
                []
            )
        );
        $createDays = $fields->firstWhere('name', 'days_of_week');
        $editDay = $fields->firstWhere('name', 'day_of_week');

        $this->assertSame('multiselect', $createDays['type'] ?? null);
        $this->assertTrue($createDays['required'] ?? false);
        $this->assertTrue($createDays['create_only'] ?? false);
        $this->assertCount(7, $createDays['options'] ?? []);
        $this->assertSame('select', $editDay['type'] ?? null);
        $this->assertTrue($editDay['edit_only'] ?? false);

        $script = (string) file_get_contents(
            public_path('js/buildino-crud.js')
        );

        $this->assertStringContainsString('field.edit_only', $script);
        $this->assertStringContainsString('savedPayload?.meta?.created_count', $script);
    }

    public function test_expense_form_collects_scope_allocation_and_payer_responsibility(): void
    {
        $fields = collect(
            config(
                'management_crud.resources.expenses.fields',
                []
            )
        );
        $block = $fields->firstWhere('name', 'block_id');
        $method = $fields->firstWhere('name', 'allocation_method');
        $payer = $fields->firstWhere('name', 'payer_responsibility');
        $status = $fields->firstWhere('name', 'status');

        $this->assertSame('expense_blocks', $block['lookup'] ?? null);
        $this->assertSame('building_id', $block['depends_on'] ?? null);
        $this->assertSame('select', $method['type'] ?? null);
        $this->assertSame(
            ['equal', 'area', 'persons'],
            collect($method['options'] ?? [])->pluck('value')->all()
        );
        $this->assertSame('select', $payer['type'] ?? null);
        $this->assertSame(
            ['resident', 'owner', 'unit'],
            collect($payer['options'] ?? [])->pluck('value')->all()
        );
        $this->assertSame('posted', $status['default'] ?? null);
    }

    private function assertOperationRouteExists(
        string $resourceKey,
        string $operationKey,
        array $operation
    ): void {
        $method =
            strtoupper(
                $operation[
                    'method'
                ] ?? 'GET'
            );

        $url =
            preg_replace(
                '/\{[^}]+\}/',
                '1',
                (string) (
                    $operation[
                        'url'
                    ] ?? ''
                )
            );

        $url =
            explode(
                '?',
                $url,
                2
            )[0];

        $this->assertNotSame(
            '',
            $url,
            "{$resourceKey} {$operationKey} has empty URL."
        );

        try {
            $route =
                Route::getRoutes()
                    ->match(
                        Request::create(
                            $url,
                            $method
                        )
                    );
        } catch (\Throwable $exception) {
            $this->fail(
                sprintf(
                    '%s %s points to missing route: %s %s. %s',
                    $resourceKey,
                    $operationKey,
                    $method,
                    $url,
                    $exception->getMessage()
                )
            );
        }

        $this->assertNotNull(
            $route,
            "{$resourceKey} {$operationKey} route was not matched."
        );
    }

    private function createManagementUser(): User
    {
        $user =
            User::factory()
                ->create([
                    'mobile_verified_at' => now(),
                    'is_active' => true,
                    'is_blocked' => false,
                ]);

        $role =
            Role::query()
                ->create([
                    'name' => 'crud-manager-'
                        .uniqid(),
                    'display_name' => 'CRUD Manager',
                    'is_system' => false,
                ]);

        $permissionNames = [
            'reports.platform.view',
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'complexes.view',
            'buildings.view',
        ];

        $permissionIds =
            collect(
                $permissionNames
            )
                ->map(
                    function (
                        string $name
                    ): int {
                        return Permission::query()
                            ->firstOrCreate(
                                [
                                    'name' => $name,
                                ],
                                [
                                    'display_name' => $name,
                                    'module' => str(
                                        $name
                                    )
                                        ->before('.')
                                        ->toString(),
                                ]
                            )
                            ->getKey();
                    }
                )
                ->all();

        $role
            ->permissions()
            ->sync(
                $permissionIds
            );

        UserRoleAssignment::query()
            ->create([
                'user_id' => $user->getKey(),
                'role_id' => $role->getKey(),
                'scope_type' => null,
                'scope_id' => null,
                'starts_at' => now()->subMinute(),
                'ends_at' => null,
                'is_active' => true,
                'assigned_by' => null,
            ]);

        return $user;
    }
}
