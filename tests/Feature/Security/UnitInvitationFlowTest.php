<?php

namespace Tests\Feature\Security;

use App\Contracts\Notifications\SmsSender;
use App\Enums\InvitationChannel;
use App\Enums\InvitationStatus;
use App\Enums\OccupancyType;
use App\Enums\UnitUsageType;
use App\Mail\UnitInvitationMail;
use App\Models\Block;
use App\Models\Building;
use App\Models\Complex;
use App\Models\Floor;
use App\Models\ManagedUserScope;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitInvitation;
use App\Models\UnitOccupancy;
use App\Models\UnitOwnership;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnitInvitationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_uses_existing_unit_user_data_without_duplicate_form_fields(): void
    {
        config([
            'unit_invitations.message' =>
                'دعوت ثابت بیلدینو برای عضویت در واحد.',
            'unit_invitations.code_digits' => 8,
        ]);

        $manager = $this->createUser(
            '09121100001',
            'manager-existing-data@example.test'
        );
        $first = $this->createUser(
            '09121100002',
            'tenant-existing@example.test'
        );
        $second = $this->createUser(
            '09121100003',
            'family-existing@example.test'
        );
        $outsider = $this->createUser(
            '09121100004',
            'outsider-existing@example.test'
        );

        $inside = $this->createStructure('EXISTING-IN');
        $outside = $this->createStructure('EXISTING-OUT');

        $role = $this->createRoleWithPermissions(
            'existing-data-invitation-manager',
            [
                'unit-invitations.view',
                'unit-invitations.create',
                'unit-invitations.update',
            ]
        );

        $this->assignRole(
            $manager,
            $role,
            $inside['building']
        );

        $this->attachOccupancy(
            $first,
            $inside['unit'],
            OccupancyType::Tenant,
            $manager
        );
        $this->attachOccupancy(
            $second,
            $inside['unit'],
            OccupancyType::FamilyMember,
            $manager
        );
        $this->attachOccupancy(
            $outsider,
            $outside['unit'],
            OccupancyType::Resident,
            $manager
        );

        $sms = new class implements SmsSender
        {
            public array $messages = [];

            public function send(
                string $mobile,
                string $message
            ): array {
                $this->messages[$mobile] = $message;

                return ['accepted' => true];
            }
        };

        $this->app->instance(
            SmsSender::class,
            $sms
        );

        $this->actingAs($manager, 'web')
            ->getJson(
                "/management/lookups/invitable_users?unit_id={$inside['unit']->id}"
            )
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'id' => $first->id,
            ])
            ->assertJsonFragment([
                'id' => $second->id,
            ])
            ->assertJsonMissing([
                'id' => $outsider->id,
            ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson(
            "/api/v1/units/{$inside['unit']->id}/invitations",
            [
                'user_ids' => [$first->id],
                'expires_in_hours' => 48,
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('meta.sent_count', 1)
            ->assertJsonPath(
                'data.0.invited_user_id',
                $first->id
            )
            ->assertJsonPath(
                'data.0.mobile',
                $first->mobile
            )
            ->assertJsonPath(
                'data.0.relation_type',
                OccupancyType::Tenant->value
            )
            ->assertJsonPath(
                'data.0.channel',
                InvitationChannel::Sms->value
            )
            ->assertJsonPath(
                'data.0.message',
                'دعوت ثابت بیلدینو برای عضویت در واحد.'
            );

        $this->assertMatchesRegularExpression(
            '/^\\d{8}$/',
            $sms->messages[$first->mobile]
        );

        /*
         * Duplicate contact / relationship fields are rejected. The server
         * owns these values and resolves them from User + Unit relations.
         */
        $this->postJson(
            "/api/v1/units/{$inside['unit']->id}/invitations",
            [
                'user_ids' => [$second->id],
                'mobile' => '09129999999',
                'relation_type' =>
                    OccupancyType::Owner->value,
                'channel' =>
                    InvitationChannel::Email->value,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'mobile',
                'relation_type',
                'channel',
                'message',
            ]);
    }

    public function test_selecting_only_unit_invites_all_linked_users_with_mobile(): void
    {
        $manager = $this->createUser(
            '09121101001',
            'manager-unit-all@example.test'
        );
        $tenant = $this->createUser(
            '09121101002',
            'tenant-unit-all@example.test'
        );
        $resident = $this->createUser(
            '09121101003',
            'resident-unit-all@example.test'
        );
        $structure = $this->createStructure('UNIT-ALL');

        $role = $this->createRoleWithPermissions(
            'unit-all-invitation-manager',
            [
                'unit-invitations.create',
            ]
        );

        $this->assignRole(
            $manager,
            $role,
            $structure['building']
        );

        $this->attachOccupancy(
            $tenant,
            $structure['unit'],
            OccupancyType::Tenant,
            $manager
        );
        $this->attachOccupancy(
            $resident,
            $structure['unit'],
            OccupancyType::Resident,
            $manager
        );

        $sms = new class implements SmsSender
        {
            public array $messages = [];

            public function send(
                string $mobile,
                string $message
            ): array {
                $this->messages[$mobile] = $message;

                return ['accepted' => true];
            }
        };

        $this->app->instance(
            SmsSender::class,
            $sms
        );

        Sanctum::actingAs($manager);

        $this->postJson(
            "/api/v1/units/{$structure['unit']->id}/invitations",
            [
                'expires_in_hours' => 72,
            ]
        )
            ->assertCreated()
            ->assertJsonPath('meta.sent_count', 2);

        $this->assertArrayHasKey(
            $tenant->mobile,
            $sms->messages
        );
        $this->assertArrayHasKey(
            $resident->mobile,
            $sms->messages
        );
    }

    public function test_manager_can_invite_only_inside_assigned_building(): void
    {
        $manager = $this->createUser(
            '09121110001',
            'manager-invite@example.test'
        );

        $invitee = $this->createUser(
            '09121110002',
            'invitee@example.test'
        );

        $structureA = $this->createStructure('INV-A');
        $structureB = $this->createStructure('INV-B');

        $role = $this->createRoleWithPermissions(
            'unit-invitation-manager',
            [
                'users.view',
                'unit-invitations.view',
                'unit-invitations.create',
                'unit-invitations.update',
            ]
        );

        $this->assignRole(
            $manager,
            $role,
            $structureA['building']
        );
        $this->attachManagedUser(
            $invitee,
            $structureA['building'],
            $manager
        );
        $this->attachOccupancy(
            $invitee,
            $structureA['unit'],
            OccupancyType::Tenant,
            $manager
        );

        Sanctum::actingAs($manager);

        $response = $this->postJson(
            "/api/v1/units/{$structureA['unit']->id}/invitations",
            [
                'user_ids' => [$invitee->id],
                'expires_in_hours' => 72,

                // Must be ignored because unit comes from route.
                'unit_id' => $structureB['unit']->id,
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.0.unit_id',
                $structureA['unit']->id
            )
            ->assertJsonPath(
                'data.0.status',
                InvitationStatus::Sent->value
            );

        $this->assertNotEmpty(
            $response->json(
                "meta.accept_tokens.{$invitee->id}"
            )
        );

        $this->postJson(
            "/api/v1/units/{$structureB['unit']->id}/invitations",
            [
                'user_ids' => [$invitee->id],
            ]
        )->assertForbidden();
    }

    public function test_matching_linked_user_can_accept_invitation_without_duplicate_occupancy(): void
    {
        $manager = $this->createUser(
            '09121120001',
            'manager-accept@example.test'
        );

        $invitee = $this->createUser(
            '09121120002',
            'invitee-accept@example.test'
        );

        $structure = $this->createStructure('ACCEPT');

        $role = $this->createRoleWithPermissions(
            'invitation-creator',
            [
                'users.view',
                'unit-invitations.create',
            ]
        );

        $this->assignRole(
            $manager,
            $role,
            $structure['building']
        );
        $this->attachManagedUser(
            $invitee,
            $structure['building'],
            $manager
        );
        $this->attachOccupancy(
            $invitee,
            $structure['unit'],
            OccupancyType::Tenant,
            $manager
        );

        Sanctum::actingAs($manager);

        $create = $this->postJson(
            "/api/v1/units/{$structure['unit']->id}/invitations",
            [
                'user_ids' => [$invitee->id],
            ]
        )->assertCreated();

        $token = $create->json(
            "meta.accept_tokens.{$invitee->id}"
        );

        Sanctum::actingAs($invitee);

        $this->postJson(
            '/api/v1/unit-invitations/accept',
            [
                'token' => $token,
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                InvitationStatus::Accepted->value
            )
            ->assertJsonPath(
                'data.accepted_user_id',
                $invitee->id
            );

        $this->assertDatabaseHas(
            'unit_occupancies',
            [
                'unit_id' => $structure['unit']->id,
                'user_id' => $invitee->id,
                'occupancy_type' => OccupancyType::Tenant->value,
                'is_active' => true,
            ]
        );

        $this->assertSame(
            1,
            UnitOccupancy::query()
                ->where(
                    'unit_id',
                    $structure['unit']->id
                )
                ->where(
                    'user_id',
                    $invitee->id
                )
                ->where('is_active', true)
                ->count()
        );
    }

    public function test_user_with_different_identity_cannot_accept_invitation(): void
    {
        $manager = $this->createUser(
            '09121130001',
            'manager-security@example.test'
        );

        $invitee = $this->createUser(
            '09121130002',
            'real-invitee@example.test'
        );

        $attacker = $this->createUser(
            '09121130003',
            'attacker@example.test'
        );

        $structure = $this->createStructure('SECURITY');

        $role = $this->createRoleWithPermissions(
            'invitation-security-manager',
            [
                'users.view',
                'unit-invitations.create',
            ]
        );

        $this->assignRole(
            $manager,
            $role,
            $structure['building']
        );
        $this->attachManagedUser(
            $invitee,
            $structure['building'],
            $manager
        );
        $this->attachOccupancy(
            $invitee,
            $structure['unit'],
            OccupancyType::Resident,
            $manager
        );

        Sanctum::actingAs($manager);

        $create = $this->postJson(
            "/api/v1/units/{$structure['unit']->id}/invitations",
            [
                'user_ids' => [$invitee->id],
            ]
        )->assertCreated();

        $token = $create->json(
            "meta.accept_tokens.{$invitee->id}"
        );

        Sanctum::actingAs($attacker);

        $this->postJson(
            '/api/v1/unit-invitations/accept',
            [
                'token' => $token,
            ]
        )->assertForbidden();

        $this->assertDatabaseMissing(
            'unit_occupancies',
            [
                'unit_id' => $structure['unit']->id,
                'user_id' => $attacker->id,
            ]
        );
    }

    public function test_expired_invitation_cannot_be_accepted(): void
    {
        $manager = $this->createUser(
            '09121140001',
            'manager-expire@example.test'
        );

        $invitee = $this->createUser(
            '09121140002',
            'invitee-expire@example.test'
        );

        $structure = $this->createStructure('EXPIRE');

        $rawToken = str_repeat(
            'x',
            64
        );

        $invitation = UnitInvitation::query()->create([
            'unit_id' => $structure['unit']->id,
            'invited_by' => $manager->id,
            'mobile' => $invitee->mobile,
            'token' => hash('sha256', $rawToken),
            'status' => InvitationStatus::Sent->value,
            'sent_at' => now()->subDays(2),
            'expires_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($invitee);

        $this->postJson(
            '/api/v1/unit-invitations/accept',
            [
                'token' => $rawToken,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('token');

        $this->assertDatabaseHas(
            'unit_invitations',
            [
                'id' => $invitation->id,
                'status' => InvitationStatus::Expired->value,
            ]
        );
    }

    public function test_resend_rotates_token_and_cancel_prevents_acceptance(): void
    {
        $manager = $this->createUser(
            '09121150001',
            'manager-resend@example.test'
        );

        $invitee = $this->createUser(
            '09121150002',
            'invitee-resend@example.test'
        );

        $structure = $this->createStructure('RESEND');

        $role = $this->createRoleWithPermissions(
            'invitation-resend-manager',
            [
                'users.view',
                'unit-invitations.create',
                'unit-invitations.update',
            ]
        );

        $this->assignRole(
            $manager,
            $role,
            $structure['building']
        );
        $this->attachManagedUser(
            $invitee,
            $structure['building'],
            $manager
        );
        $this->attachOccupancy(
            $invitee,
            $structure['unit'],
            OccupancyType::FamilyMember,
            $manager
        );

        Sanctum::actingAs($manager);

        $create = $this->postJson(
            "/api/v1/units/{$structure['unit']->id}/invitations",
            [
                'user_ids' => [$invitee->id],
            ]
        )->assertCreated();

        $invitationId = $create->json(
            'data.0.id'
        );

        $oldToken = $create->json(
            "meta.accept_tokens.{$invitee->id}"
        );

        $resend = $this->postJson(
            "/api/v1/unit-invitations/{$invitationId}/resend"
        )->assertOk();

        $newToken = $resend->json(
            'meta.accept_token'
        );

        $this->assertNotSame(
            $oldToken,
            $newToken
        );

        Sanctum::actingAs($invitee);

        $this->postJson(
            '/api/v1/unit-invitations/accept',
            [
                'token' => $oldToken,
            ]
        )->assertNotFound();

        Sanctum::actingAs($manager);

        $this->postJson(
            "/api/v1/unit-invitations/{$invitationId}/cancel"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                InvitationStatus::Cancelled->value
            );

        Sanctum::actingAs($invitee);

        $this->postJson(
            '/api/v1/unit-invitations/accept',
            [
                'token' => $newToken,
            ]
        )->assertUnprocessable();

        $this->assertDatabaseHas(
            'unit_occupancies',
            [
                'unit_id' => $structure['unit']->id,
                'user_id' => $invitee->id,
                'occupancy_type' =>
                    OccupancyType::FamilyMember->value,
                'is_active' => true,
            ]
        );
    }

    private function createUser(
        string $mobile,
        string $email
    ): User {
        return User::query()->create([
            'first_name' => 'Invitation',
            'last_name' => 'User',
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

    private function attachOccupancy(
        User $user,
        Unit $unit,
        OccupancyType $type,
        User $actor
    ): UnitOccupancy {
        return UnitOccupancy::query()->create([
            'unit_id' => $unit->id,
            'user_id' => $user->id,
            'occupancy_type' => $type->value,
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => null,
            'is_primary' => false,
            'is_active' => true,
            'created_by' => $actor->id,
        ]);
    }

    private function attachManagedUser(
        User $user,
        mixed $scope,
        User $actor
    ): ManagedUserScope {
        return ManagedUserScope::query()->create([
            'user_id' => $user->id,
            'scope_type' => $scope->getMorphClass(),
            'scope_id' => $scope->getKey(),
            'assigned_by' => $actor->id,
        ]);
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
