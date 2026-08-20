<?php

namespace Tests\Feature\Web;

use App\Contracts\Auth\OtpSender;
use App\Enums\InvitationChannel;
use App\Enums\InvitationStatus;
use App\Enums\OccupancyType;
use App\Models\Building;
use App\Models\Complex;
use App\Models\UnitInvitation;
use App\Models\UnitOccupancy;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\Web\ManagementDashboardAccessService;
use Database\Seeders\RoleMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

final class SelfRegistrationWebTest extends TestCase
{
    use CreatesBuildingDomainData;
    use RefreshDatabase;

    private object $otpSender;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleMatrixSeeder::class);

        $this->otpSender = new class implements OtpSender
        {
            /** @var array<string, string> */
            public array $codes = [];

            public function send(
                string $identifier,
                string $channel,
                string $code
            ): void {
                $this->codes[$identifier] = $code;
            }
        };

        $this->app->instance(
            OtpSender::class,
            $this->otpSender
        );
    }

    public function test_login_pages_offer_role_aware_registration(): void
    {
        $this->get('/management/login')
            ->assertOk()
            ->assertSee('ساخت حساب جدید')
            ->assertSee(route('register', [
                'persona' => 'building_manager',
            ]), false);

        $this->get('/portal/login')
            ->assertOk()
            ->assertSee('ایجاد حساب کاربری')
            ->assertSee(route('register', [
                'persona' => 'tenant',
            ]), false);

        $this->get('/register')
            ->assertOk()
            ->assertSee('مدیر ساختمان')
            ->assertSee('مدیر مجتمع')
            ->assertSee('مدیر مالی')
            ->assertSee('اپراتور ساختمان')
            ->assertSee('کارشناس پشتیبانی')
            ->assertSee('ارائه‌دهنده خدمات')
            ->assertSee('مالک واحد')
            ->assertSee('مستأجر یا ساکن');
    }

    #[DataProvider('managementPersonaProvider')]
    public function test_management_personas_receive_an_isolated_workspace_after_otp(
        string $persona,
        string $expectedScopeClass
    ): void {
        $mobile = match ($persona) {
            'building_manager' => '09120000101',
            'complex_manager' => '09120000102',
            'finance_manager' => '09120000103',
            'operator' => '09120000104',
            default => '09120000105',
        };

        $this->post('/register', $this->managementPayload(
            $persona,
            $mobile
        ))
            ->assertRedirect(
                route('register.verify')
            );

        $this->assertDatabaseMissing('users', [
            'mobile' => $mobile,
        ]);
        $this->assertDatabaseCount('complexes', 0);
        $this->assertDatabaseCount('buildings', 0);

        $this->post('/register/verify', [
            'code' => $this->otpCode($mobile),
        ])->assertRedirect(
            route('management.dashboard')
        );

        $user = User::query()
            ->where('mobile', $mobile)
            ->firstOrFail();
        $building = Building::query()
            ->firstOrFail();
        $assignment = UserRoleAssignment::query()
            ->where('user_id', $user->getKey())
            ->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull(
            $user->mobile_verified_at
        );
        $this->assertSame(
            $persona,
            $assignment->role->name
        );
        $this->assertSame(
            (new $expectedScopeClass)->getMorphClass(),
            $assignment->scope_type
        );
        $this->assertSame(
            $expectedScopeClass === Complex::class
                ? $building->complex_id
                : $building->getKey(),
            (int) $assignment->scope_id
        );

        $this->get('/management')->assertOk();

        $foreign = $this->createBuildingGraph();
        $accessible = $this->app
            ->make(
                ManagementDashboardAccessService::class
            )
            ->accessibleBuildings($user);

        $this->assertTrue(
            $accessible->contains('id', $building->id)
        );
        $this->assertFalse(
            $accessible->contains(
                'id',
                $foreign['building']->id
            )
        );
    }

    public static function managementPersonaProvider(): array
    {
        return [
            'building manager' => [
                'building_manager',
                Building::class,
            ],
            'complex manager' => [
                'complex_manager',
                Complex::class,
            ],
            'finance manager' => [
                'finance_manager',
                Building::class,
            ],
            'operator' => [
                'operator',
                Building::class,
            ],
            'support agent' => [
                'support_agent',
                Building::class,
            ],
        ];
    }

    public function test_registered_building_manager_can_create_blocks_floors_and_units(): void
    {
        $mobile = '09120000201';

        $this->completeRegistration(
            $this->managementPayload(
                'building_manager',
                $mobile
            ),
            $mobile
        );

        $building = Building::query()
            ->firstOrFail();

        $block = $this->postJson(
            "/api/v1/buildings/{$building->id}/blocks",
            [
                'title' => 'بلوک A',
                'is_active' => true,
            ]
        )
            ->assertCreated()
            ->json('data');

        $floor = $this->postJson(
            "/api/v1/blocks/{$block['id']}/floors",
            [
                'floor_number' => 1,
                'title' => 'طبقه اول',
            ]
        )
            ->assertCreated()
            ->json('data');

        $this->postJson(
            "/api/v1/floors/{$floor['id']}/units",
            [
                'unit_number' => '101',
                'title' => 'واحد ۱۰۱',
                'area' => 95,
                'bedrooms' => 2,
                'usage_type' => 'residential',
                'is_active' => true,
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.unit_number',
                '101'
            );
    }

    public function test_service_provider_registers_without_a_management_workspace(): void
    {
        $mobile = '09120000301';

        $this->completeRegistration([
            'persona' => 'service_provider',
            'first_name' => 'رضا',
            'last_name' => 'خدمت‌رسان',
            'mobile' => $mobile,
            'email' => 'provider-register@example.test',
            'password' => 'Provider123',
            'password_confirmation' => 'Provider123',
            'terms' => '1',
        ], $mobile, 'portal.provider.dashboard');

        $user = User::query()
            ->where('mobile', $mobile)
            ->firstOrFail();
        $assignment = $user
            ->userRoleAssignments()
            ->firstOrFail();

        $this->assertTrue(
            $user->hasRole('service_provider')
        );
        $this->assertNull($assignment->scope_type);
        $this->assertNull($assignment->scope_id);
        $this->assertDatabaseCount('complexes', 0);
        $this->assertDatabaseCount('buildings', 0);

        $this->get('/portal/provider')->assertOk();
        $this->get('/management')
            ->assertRedirect('/management/login');
    }

    public function test_owner_can_register_only_with_a_matching_active_unit_invitation(): void
    {
        $mobile = '09120000401';
        $rawToken = Str::random(64);
        $inviter = $this->createUser([
            'mobile' => '09129990401',
            'email' => 'inviter-owner@example.test',
            'mobile_verified_at' => now(),
        ]);
        $structure = $this->createBuildingGraph();

        $invitation = UnitInvitation::query()->create([
            'unit_id' => $structure['unit']->id,
            'invited_by' => $inviter->id,
            'mobile' => $mobile,
            'relation_type' => OccupancyType::Owner,
            'channel' => InvitationChannel::Sms,
            'token' => hash('sha256', $rawToken),
            'status' => InvitationStatus::Sent,
            'sent_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        $this->get(
            '/invitations/accept?token='.$rawToken
        )->assertRedirect(route('register', [
            'persona' => 'owner',
            'invitation_token' => $rawToken,
        ]));

        $this->completeRegistration([
            'persona' => 'owner',
            'first_name' => 'مریم',
            'last_name' => 'مالک',
            'mobile' => $mobile,
            'email' => 'new-owner@example.test',
            'password' => 'Owner1234',
            'password_confirmation' => 'Owner1234',
            'invitation_token' => $rawToken,
            'terms' => '1',
        ], $mobile, 'portal.resident.dashboard');

        $user = User::query()
            ->where('mobile', $mobile)
            ->firstOrFail();
        $assignment = $user
            ->userRoleAssignments()
            ->firstOrFail();
        $occupancy = UnitOccupancy::query()
            ->where('unit_id', $structure['unit']->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertSame(
            'owner',
            $assignment->role->name
        );
        $this->assertSame(
            $structure['unit']->getMorphClass(),
            $assignment->scope_type
        );
        $this->assertSame(
            $structure['unit']->id,
            (int) $assignment->scope_id
        );
        $this->assertSame(
            OccupancyType::Owner,
            $occupancy->occupancy_type
        );
        $this->assertSame(
            InvitationStatus::Accepted,
            $invitation->fresh()->status
        );

        $this->get('/portal/resident')
            ->assertOk()
            ->assertSee('مالک');
    }

    public function test_resident_cannot_claim_a_unit_or_publicly_register_as_superadmin(): void
    {
        $this->post('/register', [
            'persona' => 'tenant',
            'first_name' => 'کاربر',
            'last_name' => 'بدون دعوت',
            'mobile' => '09120000501',
            'password' => 'Tenant123',
            'password_confirmation' => 'Tenant123',
            'terms' => '1',
        ])
            ->assertSessionHasErrors(
                'invitation_token'
            );

        $this->post('/register', [
            'persona' => 'superadmin',
            'first_name' => 'مدیر',
            'last_name' => 'جعلی',
            'mobile' => '09120000502',
            'password' => 'Admin1234',
            'password_confirmation' => 'Admin1234',
            'terms' => '1',
        ])->assertSessionHasErrors('persona');

        $this->assertDatabaseMissing('users', [
            'mobile' => '09120000501',
        ]);
        $this->assertDatabaseMissing('users', [
            'mobile' => '09120000502',
        ]);
        $this->assertSame([], $this->otpSender->codes);
    }

    private function completeRegistration(
        array $payload,
        string $mobile,
        string $destination = 'management.dashboard'
    ): void {
        $this->post('/register', $payload)
            ->assertRedirect(
                route('register.verify')
            );

        $this->post('/register/verify', [
            'code' => $this->otpCode($mobile),
        ])->assertRedirect(
            route($destination)
        );
    }

    private function managementPayload(
        string $persona,
        string $mobile
    ): array {
        return [
            'persona' => $persona,
            'first_name' => 'علی',
            'last_name' => 'مدیر',
            'mobile' => $mobile,
            'email' => $persona.'@register.example.test',
            'password' => 'Manager123',
            'password_confirmation' => 'Manager123',
            'complex_title' => 'مجتمع آزمایشی',
            'building_title' => 'ساختمان اول',
            'province' => 'تهران',
            'city' => 'تهران',
            'address' => 'تهران، خیابان آزمایش',
            'postal_code' => '1234567890',
            'terms' => '1',
        ];
    }

    private function otpCode(string $mobile): string
    {
        $this->assertArrayHasKey(
            $mobile,
            $this->otpSender->codes
        );

        return $this->otpSender->codes[$mobile];
    }
}
