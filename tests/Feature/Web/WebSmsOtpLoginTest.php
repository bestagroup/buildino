<?php

namespace Tests\Feature\Web;

use App\Contracts\Auth\OtpSender;
use App\Models\Block;
use App\Models\Building;
use App\Models\Complex;
use App\Models\Role;
use App\Models\UnitOccupancy;
use App\Models\UnitOwnership;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Database\Seeders\RoleMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

final class WebSmsOtpLoginTest extends TestCase
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

    public function test_management_and_portal_login_pages_expose_sms_login(): void
    {
        $this->get('/management/login')
            ->assertOk()
            ->assertSee('کد پیامکی')
            ->assertSee(
                route('management.login.otp.request'),
                false
            )
            ->assertSee(
                asset('js/buildino-auth-login.js'),
                false
            );

        $this->get('/portal/login')
            ->assertOk()
            ->assertSee('کد پیامکی')
            ->assertSee(
                route('portal.login.otp.request'),
                false
            )
            ->assertSee(
                asset('js/buildino-auth-login.js'),
                false
            );
    }

    #[DataProvider('managementRoleProvider')]
    public function test_every_management_role_can_login_with_sms(
        string $roleName,
        string $mobile,
        ?string $scopeClass
    ): void {
        $structure = $this->createBuildingGraph();
        $user = $this->unverifiedUser(
            $mobile,
            $roleName.'@otp-management.example.test'
        );
        $scope = match ($scopeClass) {
            Complex::class => $structure['complex'],
            Building::class => $structure['building'],
            Block::class => $structure['block'],
            default => null,
        };

        $this->assignRole(
            $user,
            $roleName,
            $scope
        );

        $submittedMobile = $roleName
            === 'building_manager'
                ? $this->toPersianDigits($mobile)
                : $mobile;

        $this->post(
            '/management/login/otp/request',
            [
                'mobile' => $submittedMobile,
                'auth_method' => 'otp',
            ]
        )
            ->assertRedirect('/management/login')
            ->assertSessionHas('auth_method', 'otp')
            ->assertSessionHas('otp_status')
            ->assertSessionHas(
                'buildino.web_otp.management.mobile',
                $mobile
            );

        $this->post(
            '/management/login/otp/verify',
            [
                'code' => $this->otpCode($mobile),
                'auth_method' => 'otp',
            ]
        )->assertRedirect(
            route('management.dashboard')
        )->assertSessionHas('buildino.login_success', true);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull(
            $user->fresh()->mobile_verified_at
        );
        $this->assertNotNull(
            $user->fresh()->last_login_at
        );
        $this->get('/management')->assertOk();
    }

    public static function managementRoleProvider(): array
    {
        return [
            'superadmin' => [
                'superadmin',
                '09121100100',
                null,
            ],
            'complex manager' => [
                'complex_manager',
                '09121100101',
                Complex::class,
            ],
            'building manager' => [
                'building_manager',
                '09121100102',
                Building::class,
            ],
            'block manager' => [
                'block_manager',
                '09121100106',
                Block::class,
            ],
            'finance manager' => [
                'finance_manager',
                '09121100103',
                Building::class,
            ],
            'operator' => [
                'operator',
                '09121100104',
                Building::class,
            ],
            'support agent' => [
                'support_agent',
                '09121100105',
                Building::class,
            ],
        ];
    }

    #[DataProvider('portalPersonaProvider')]
    public function test_every_portal_persona_can_login_with_sms(
        string $persona,
        string $mobile,
        string $expectedArea
    ): void {
        $structure = $this->createBuildingGraph();
        $user = $this->unverifiedUser(
            $mobile,
            $persona.'@otp-portal.example.test'
        );

        if ($persona === 'service_provider') {
            $this->assignRole(
                $user,
                'service_provider',
                null
            );
        } elseif ($persona === 'owner') {
            UnitOwnership::query()->create([
                'unit_id' => $structure['unit']->id,
                'user_id' => $user->id,
                'ownership_percentage' => 100,
                'starts_at' => now()->toDateString(),
                'ends_at' => null,
                'is_primary' => true,
                'is_active' => true,
                'created_by' => $user->id,
            ]);
        } else {
            UnitOccupancy::query()->create([
                'unit_id' => $structure['unit']->id,
                'user_id' => $user->id,
                'occupancy_type' => 'tenant',
                'starts_at' => now()->toDateString(),
                'ends_at' => null,
                'is_primary' => true,
                'is_active' => true,
                'created_by' => $user->id,
            ]);
        }

        $this->post('/portal/login/otp/request', [
            'mobile' => '+98'.substr($mobile, 1),
            'auth_method' => 'otp',
        ])
            ->assertRedirect('/portal/login')
            ->assertSessionHas('auth_method', 'otp')
            ->assertSessionHas(
                'buildino.web_otp.portal.mobile',
                $mobile
            );

        $this->post('/portal/login/otp/verify', [
            'code' => $this->otpCode($mobile),
            'auth_method' => 'otp',
        ])->assertRedirect(
            route('portal.dashboard')
        )->assertSessionHas('buildino.login_success', true);

        $this->assertAuthenticatedAs($user);
        $this->get('/portal')
            ->assertRedirect(
                route("portal.{$expectedArea}.dashboard")
            )
            ->assertSessionHas('buildino.login_success', true);

        $this->get(route("portal.{$expectedArea}.dashboard"))
            ->assertOk()
            ->assertSee('data-login-success', false);
        $this->get(route("portal.{$expectedArea}.dashboard"))
            ->assertOk()
            ->assertDontSee('data-login-success', false);
    }

    public static function portalPersonaProvider(): array
    {
        return [
            'owner' => [
                'owner',
                '09121100201',
                'resident',
            ],
            'tenant' => [
                'tenant',
                '09121100202',
                'resident',
            ],
            'service provider' => [
                'service_provider',
                '09121100203',
                'provider',
            ],
        ];
    }

    public function test_otp_is_not_sent_to_unknown_blocked_or_wrong_area_accounts(): void
    {
        $structure = $this->createBuildingGraph();
        $managementUser = $this->unverifiedUser(
            '09121100301',
            'management-only-otp@example.test'
        );
        $this->assignRole(
            $managementUser,
            'building_manager',
            $structure['building']
        );

        $blockedProvider = $this->unverifiedUser(
            '09121100302',
            'blocked-provider-otp@example.test',
            true
        );
        $this->assignRole(
            $blockedProvider,
            'service_provider',
            null
        );

        foreach ([
            '09121100301',
            '09121100302',
            '09121100303',
        ] as $mobile) {
            $this->post('/portal/login/otp/request', [
                'mobile' => $mobile,
                'auth_method' => 'otp',
            ])
                ->assertRedirect('/portal/login')
                ->assertSessionHas('otp_status');
        }

        $this->assertSame([], $this->otpSender->codes);
        $this->assertDatabaseCount('otp_codes', 0);
    }

    public function test_wrong_sms_code_never_creates_a_web_session(): void
    {
        $structure = $this->createBuildingGraph();
        $user = $this->unverifiedUser(
            '09121100401',
            'wrong-code-otp@example.test'
        );
        $this->assignRole(
            $user,
            'building_manager',
            $structure['building']
        );

        $this->post('/management/login/otp/request', [
            'mobile' => $user->mobile,
            'auth_method' => 'otp',
        ])->assertRedirect('/management/login')->assertSessionMissing('buildino.login_success');

        $this->post('/management/login/otp/verify', [
            'code' => '000000',
            'auth_method' => 'otp',
        ])->assertSessionHasErrors('code')
            ->assertSessionMissing('buildino.login_success');

        $this->assertGuest();
        $this->assertNull(
            $user->fresh()->mobile_verified_at
        );
        $this->assertDatabaseHas('otp_codes', [
            'identifier' => $user->mobile,
            'purpose' => 'web_management_login',
            'attempts' => 1,
        ]);
    }

    #[DataProvider('loginAreaProvider')]
    public function test_requesting_sms_replaces_the_mobile_form_with_the_code_form(string $area): void
    {
        $this->post('/'.$area.'/login/otp/request', [
            'mobile' => '09121100999',
            'auth_method' => 'otp',
        ])->assertRedirect('/'.$area.'/login');

        $response = $this->get('/'.$area.'/login')->assertOk();
        $this->assertRenderedOtpStage($response->getContent(), true);
    }

    #[DataProvider('loginAreaProvider')]
    public function test_invalid_changed_mobile_keeps_the_number_form_visible(string $area): void
    {
        $this->withSession(['buildino.web_otp.'.$area.'.mobile' => '09121100999'])
            ->from('/'.$area.'/login')
            ->post('/'.$area.'/login/otp/request', [
                'mobile' => 'invalid-mobile',
                'auth_method' => 'otp',
            ])->assertSessionHasErrors('mobile');

        $response = $this->get('/'.$area.'/login')->assertOk();
        $this->assertRenderedOtpStage($response->getContent(), false);
    }

    public static function loginAreaProvider(): array
    {
        return ['management' => ['management'], 'portal' => ['portal']];
    }

    private function assertRenderedOtpStage(string $html, bool $verify): void
    {
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($document);
        $request = $xpath->query('//*[@data-otp-request-form]')->item(0);
        $verification = $xpath->query('//*[@data-otp-verification]')->item(0);
        $this->assertNotNull($request);
        $this->assertNotNull($verification);
        $this->assertSame($verify, $request->hasAttribute('hidden'));
        $this->assertSame(! $verify, $verification->hasAttribute('hidden'));
        $this->assertStringNotContainsString('@hidden(', $html);
    }

    private function unverifiedUser(
        string $mobile,
        string $email,
        bool $blocked = false
    ): User {
        return $this->createUser([
            'mobile' => $mobile,
            'email' => $email,
            'mobile_verified_at' => null,
            'email_verified_at' => null,
            'is_blocked' => $blocked,
        ]);
    }

    private function assignRole(
        User $user,
        string $roleName,
        Complex|Building|Block|null $scope
    ): void {
        $role = Role::query()
            ->where('name', $roleName)
            ->firstOrFail();

        UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => $scope?->getMorphClass(),
            'scope_id' => $scope?->getKey(),
            'starts_at' => now(),
            'is_active' => true,
            'assigned_by' => $user->id,
        ]);
    }

    private function otpCode(string $mobile): string
    {
        $this->assertArrayHasKey(
            $mobile,
            $this->otpSender->codes
        );

        return $this->otpSender->codes[$mobile];
    }

    private function toPersianDigits(
        string $value
    ): string {
        return strtr($value, [
            '0' => '۰', '1' => '۱',
            '2' => '۲', '3' => '۳',
            '4' => '۴', '5' => '۵',
            '6' => '۶', '7' => '۷',
            '8' => '۸', '9' => '۹',
        ]);
    }
}
