<?php

namespace Tests\Feature\Web;

use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AccessScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalWebAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_views_resolve_authenticated_user_without_management_view_composer(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $owner =
            $this->user(
                'role.owner@buildino.local'
            );

        $this->actingAs(
            $owner,
            'web'
        );

        $this->get(
            '/portal/resident'
        )
            ->assertOk()
            ->assertSee(
                $owner->first_name
            );
    }

    public function test_owner_can_login_to_resident_portal_and_sees_only_owned_unit(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $owner =
            $this->user(
                'role.owner@buildino.local'
            );

        $this->post(
            '/portal/login',
            [
                'login' =>
                    $owner->mobile,

                'password' =>
                    'Demo@1405',
            ]
        )
            ->assertRedirect(
                '/portal'
            );

        $this->get(
            '/portal/resident'
        )
            ->assertOk()
            ->assertSee(
                'خانه من'
            )
            ->assertSee(
                'واحد 101'
            )
            ->assertDontSee(
                'واحد 102'
            )
            ->assertSee(
                'ثبت مهمان'
            )
            ->assertSee(
                'درخواست خدمت'
            )
            ->assertSee(
                'باشگاه وفاداری'
            );
    }

    public function test_tenant_can_login_and_sees_only_occupied_unit(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $tenant =
            $this->user(
                'role.tenant@buildino.local'
            );

        $this->actingAs(
            $tenant,
            'web'
        );

        $this->get(
            '/portal/resident'
        )
            ->assertOk()
            ->assertSee(
                'واحد 102'
            )
            ->assertDontSee(
                'واحد 101'
            );
    }

    public function test_provider_gets_provider_portal_and_cannot_open_resident_area(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $provider =
            $this->user(
                'role.provider@buildino.local'
            );

        $this->post(
            '/portal/login',
            [
                'login' =>
                    $provider->mobile,

                'password' =>
                    'Demo@1405',
            ]
        )
            ->assertRedirect(
                '/portal'
            );

        $this->get(
            '/portal/provider'
        )
            ->assertOk()
            ->assertSee(
                'پنل ارائه‌دهنده خدمات'
            )
            ->assertSee(
                'حساب بانکی جدید'
            )
            ->assertSee(
                'درخواست تسویه'
            );

        $this->get(
            '/portal/resident'
        )
            ->assertForbidden();
    }

    public function test_owner_cannot_open_provider_area(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $owner =
            $this->user(
                'role.owner@buildino.local'
            );

        $this->actingAs(
            $owner,
            'web'
        );

        $this->get(
            '/portal/provider'
        )
            ->assertForbidden();
    }

    public function test_management_only_user_is_rejected_by_portal_login(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $superadmin =
            $this->user(
                'role.superadmin@buildino.local'
            );

        $this->post(
            '/portal/login',
            [
                'login' =>
                    $superadmin->mobile,

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

    public function test_resident_web_session_can_read_its_unit_invoice_api(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $owner =
            $this->user(
                'role.owner@buildino.local'
            );

        $unit =
            Unit::query()
                ->where(
                    'unit_number',
                    '101'
                )
                ->whereHas(
                    'unitOwnerships',
                    fn ($query) =>
                        $query->where(
                            'user_id',
                            $owner->getKey()
                        )
                )
                ->firstOrFail();

        $this->actingAs(
            $owner,
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
                . '/portal/resident',
        ])
            ->getJson(
                "/api/v1/units/{$unit->getKey()}/invoices"
            )
            ->assertOk();
    }

    public function test_resident_cannot_create_service_or_support_request_for_another_unit_in_same_building(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $owner =
            $this->user(
                'role.owner@buildino.local'
            );

        $ownedUnit =
            Unit::query()
                ->where(
                    'unit_number',
                    '101'
                )
                ->whereHas(
                    'unitOwnerships',
                    fn ($query) =>
                        $query->where(
                            'user_id',
                            $owner->getKey()
                        )
                )
                ->firstOrFail();

        $ownedUnit->loadMissing(
            'floor.block.building'
        );

        $otherUnit =
            Unit::query()
                ->where(
                    'unit_number',
                    '102'
                )
                ->whereHas(
                    'floor.block',
                    fn ($query) =>
                        $query->where(
                            'building_id',
                            $ownedUnit
                                ->floor
                                ->block
                                ->building_id
                        )
                )
                ->firstOrFail();

        $this->actingAs(
            $owner,
            'web'
        );

        $headers = [
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
                . '/portal/resident',
        ];

        $this->withHeaders(
            $headers
        )
            ->postJson(
                '/api/v1/service-requests',
                [
                    'building_id' =>
                        $ownedUnit
                            ->floor
                            ->block
                            ->building_id,
                    'unit_id' =>
                        $otherUnit
                            ->getKey(),
                    'type' =>
                        'electrical',
                    'priority' =>
                        'normal',
                    'title' =>
                        'Cross unit service request',
                    'description' =>
                        'Must be denied.',
                ]
            )
            ->assertForbidden();

        $this->withHeaders(
            $headers
        )
            ->postJson(
                '/api/v1/support-tickets',
                [
                    'building_id' =>
                        $ownedUnit
                            ->floor
                            ->block
                            ->building_id,
                    'unit_id' =>
                        $otherUnit
                            ->getKey(),
                    'subject' =>
                        'Cross unit support ticket',
                    'description' =>
                        'Must be denied.',
                    'priority' =>
                        'medium',
                ]
            )
            ->assertForbidden();
    }

    public function test_provider_dashboard_lists_only_jobs_assigned_to_provider(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $provider =
            $this->user(
                'role.provider@buildino.local'
            );

        $other =
            $this->user(
                'role.operator@buildino.local'
            );

        $unit =
            Unit::query()
                ->where(
                    'unit_number',
                    '101'
                )
                ->firstOrFail();

        $unit->loadMissing(
            'floor.block.building'
        );

        $building =
            $unit->floor
                ->block
                ->building;

        ServiceRequest::query()
            ->create([
                'request_number' =>
                    'PORTAL-PROVIDER-001',

                'building_id' =>
                    $building
                        ->getKey(),

                'unit_id' =>
                    $unit
                        ->getKey(),

                'requested_by' =>
                    $other
                        ->getKey(),

                'type' =>
                    'electrical',

                'priority' =>
                    ServiceRequestPriority::Normal,

                'status' =>
                    ServiceRequestStatus::Assigned,

                'title' =>
                    'کار اختصاصی Provider Portal',

                'description' =>
                    'Provider must see this request.',

                'assigned_to' =>
                    $provider
                        ->getKey(),

                'assigned_at' =>
                    now(),
            ]);

        ServiceRequest::query()
            ->create([
                'request_number' =>
                    'PORTAL-OTHER-001',

                'building_id' =>
                    $building
                        ->getKey(),

                'unit_id' =>
                    $unit
                        ->getKey(),

                'requested_by' =>
                    $provider
                        ->getKey(),

                'type' =>
                    'cleaning',

                'priority' =>
                    ServiceRequestPriority::Low,

                'status' =>
                    ServiceRequestStatus::Assigned,

                'title' =>
                    'کار متعلق به Provider دیگر',

                'description' =>
                    'Current provider must not see this.',

                'assigned_to' =>
                    $other
                        ->getKey(),

                'assigned_at' =>
                    now(),
            ]);

        $this->actingAs(
            $provider,
            'web'
        );

        $this->get(
            '/portal/provider'
        )
            ->assertOk()
            ->assertSee(
                'کار اختصاصی Provider Portal'
            )
            ->assertDontSee(
                'کار متعلق به Provider دیگر'
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
