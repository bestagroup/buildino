<?php

namespace Tests\Feature\Web;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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
            'Origin' =>
                config(
                    'app.url',
                    'http://localhost'
                ),
            'Referer' =>
                rtrim(
                    config(
                        'app.url',
                        'http://localhost'
                    ),
                    '/'
                )
                . '/management/operations/complexes',
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
                    'first_name' =>
                        'کاربر',
                    'last_name' =>
                        'آزمایشی',
                    'national_code' =>
                        '0012345678',
                    'mobile' =>
                        '09121112233',
                    'email' =>
                        'crud-user@buildino.local',
                    'password' =>
                        'Password@123',
                    'verify_mobile' =>
                        true,
                    'is_active' =>
                        true,
                    'is_blocked' =>
                        false,
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
                'first_name' =>
                    'ویرایش',
                'is_blocked' =>
                    true,
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
            $resources
            as $resourceKey => $resource
        ) {
            foreach (
                [
                    'list',
                    'show',
                    'create',
                    'update',
                    'delete',
                ]
                as $operationKey
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
                $resource['actions'] ?? []
                as $action
            ) {
                $this->assertOperationRouteExists(
                    $resourceKey,
                    'action:'
                    . (
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
                    'mobile_verified_at' =>
                        now(),
                    'is_active' =>
                        true,
                    'is_blocked' =>
                        false,
                ]);

        $role =
            Role::query()
                ->create([
                    'name' =>
                        'crud-manager-'
                        . uniqid(),
                    'display_name' =>
                        'CRUD Manager',
                    'is_system' =>
                        false,
                ]);

        $permissionNames = [
            'reports.platform.view',
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'complexes.view',
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
                                    'name' =>
                                        $name,
                                ],
                                [
                                    'display_name' =>
                                        $name,
                                    'module' =>
                                        str(
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
                'user_id' =>
                    $user->getKey(),
                'role_id' =>
                    $role->getKey(),
                'scope_type' =>
                    null,
                'scope_id' =>
                    null,
                'starts_at' =>
                    now()->subMinute(),
                'ends_at' =>
                    null,
                'is_active' =>
                    true,
                'assigned_by' =>
                    null,
            ]);

        return $user;
    }
}
