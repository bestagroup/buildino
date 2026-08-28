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

    public function test_manager_can_send_scoped_bulk_invitations_with_custom_text(): void
    {
        $manager = $this->createUser(
            '09121100001',
            'bulk-manager@example.test'
        );
        $first = $this->createUser(
            '09121100002',
            'bulk-first@example.test'
        );
        $second = $this->createUser(
            '09121100003',
            'bulk-second@example.test'
        );
        $outsider = $this->createUser(
            '09121100004',
            'bulk-outsider@example.test'
        );
        $inside = $this->createStructure('BULK-IN');
        $outside = $this->createStructure('BULK-OUT');
        $role = $this->createRoleWithPermissions(
            'bulk-invitation-manager',
            [
                'reports.dashboard.view',
                'users.view',
                'unit-invitations.view',
                'unit-invitations.create',
            ]
        );

        $this->assignRole($manager, $role, $inside['building']);

        foreach ([$first, $second] as $user) {
            ManagedUserScope::query()->create([
                'user_id' => $user->id,
                'scope_type' => $inside['building']->getMorphClass(),
                'scope_id' => $inside['building']->id,
                'assigned_by' => $manager->id,
            ]);
        }

        ManagedUserScope::query()->create([
            'user_id' => $outsider->id,
            'scope_type' => $outside['building']->getMorphClass(),
            'scope_id' => $outside['building']->id,
            'assigned_by' => $manager->id,
        ]);

        $sms = new class implements SmsSender
        {
            public array $messages = [];

            public function send(string $mobile, string $message): array
            {
                $this->messages[$mobile] = $message;

                return ['accepted' => true];
            }
        };

        $this->app->instance(SmsSender::class, $sms);
        Mail::fake();

        $this->actingAs($manager, 'web')
            ->getJson(
                "/management/lookups/invitable_users?unit_id={$inside['unit']->id}"
            )
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $first->id])
            ->assertJsonFragment(['id' => $second->id])
            ->assertJsonMissing(['id' => $outsider->id]);

        Sanctum::actingAs($manager);

        $smsResponse = $this->postJson(
            "/api/v1/units/{$inside['unit']->id}/invitations",
            [
                'user_ids' => [$first->id, $second->id],
                'relation_type' => OccupancyType::Tenant->value,
                'channel' => InvitationChannel::Sms->value,
                'message' => 'لطفاً دعوت عضویت در واحد را بررسی کنید.',
                'expires_in_hours' => 48,
            ]
        );

        $smsResponse
            ->assertCreated()
            ->assertJsonPath('meta.sent_count', 2)
            ->assertJsonPath('data.0.invited_user_id', $first->id)
            ->assertJsonPath(
                'data.0.message',
                'لطفاً دعوت عضویت در واحد را بررسی کنید.'
            );

        $this->assertCount(2, $sms->messages);
        $this->assertStringContainsString(
            'لطفاً دعوت عضویت در واحد را بررسی کنید.',
            $sms->messages[$first->mobile]
        );
        $this->assertStringContainsString(
            '/invitations/accept?token=',
            $sms->messages[$first->mobile]
        );

        $this->postJson(
            "/api/v1/units/{$inside['unit']->id}/invitations",
            [
                'user_ids' => [$first->id, $second->id],
                'relation_type' => OccupancyType::FamilyMember->value,
                'channel' => InvitationChannel::Email->value,
                'message' => 'متن اختصاصی دعوت ایمیلی',
            ]
        )
            ->assertCreated()
            ->assertJsonPath('meta.sent_count', 2);

        Mail::assertSent(
            UnitInvitationMail::class,
            2
        );
        Mail::assertSent(
            UnitInvitationMail::class,
            fn (UnitInvitationMail $mail): bool =>
                str_contains(
                    $mail->bodyText,
                    'متن اختصاصی دعوت ایمیلی'
                )
                && str_contains(
                    $mail->bodyText,
                    '/invitations/accept?token='
                )
        );

        $this->postJson(
            "/api/v1/units/{$inside['unit']->id}/invitations",
            [
                'user_ids' => [$first->id, $outsider->id],
                'relation_type' => OccupancyType::Owner->value,
                'channel' => InvitationChannel::Sms->value,
                'message' => 'این درخواست نباید ارسال شود.',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_ids');

        $this->assertDatabaseCount('unit_invitations', 4);

        $this->postJson(
            "/api/v1/units/{$inside['unit']->id}/invitations",
            [
                'mobile' => '09129999999',
                'relation_type' => OccupancyType::Resident->value,
                'channel' => InvitationChannel::Sms->value,
                'message' => 'دعوت با شماره خام مجاز نیست.',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'user_ids',
                'mobile',
            ]);
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

        Sanctum::actingAs($manager);

        $response = $this->postJson(
            "/api/v1/units/{$structureA['unit']->id}/invitations",
            [
                'user_ids' => [$invitee->id],
                'relation_type' => OccupancyType::Tenant->value,
                'channel' => InvitationChannel::Sms->value,
                'message' => 'دعوت عضویت در واحد',
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
                'relation_type' => OccupancyType::Tenant->value,
                'channel' => InvitationChannel::Sms->value,
                'message' => 'دعوت خارج از محدوده',
            ]
        )->assertForbidden();
    }

    public function test_matching_authenticated_user_can_accept_invitation_and_becomes_occupant(): void
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

        Sanctum::actingAs($manager);

        $create = $this->postJson(
            "/api/v1/units/{$structure['unit']->id}/invitations",
            [
                'user_ids' => [$invitee->id],
                'relation_type' => OccupancyType::Tenant->value,
                'channel' => InvitationChannel::Sms->value,
                'message' => 'دعوت برای سکونت در واحد',
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

        /*
         * Invitation acceptance must never infer legal ownership.
         */
        $this->assertDatabaseMissing(
            'unit_ownerships',
            [
                'unit_id' => $structure['unit']->id,
                'user_id' => $invitee->id,
            ]
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

        Sanctum::actingAs($manager);

        $create = $this->postJson(
            "/api/v1/units/{$structure['unit']->id}/invitations",
            [
                'user_ids' => [$invitee->id],
                'relation_type' => OccupancyType::Resident->value,
                'channel' => InvitationChannel::Sms->value,
                'message' => 'دعوت امن واحد',
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
            'relation_type' => OccupancyType::Resident->value,
            'channel' => InvitationChannel::Sms->value,
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

        Sanctum::actingAs($manager);

        $create = $this->postJson(
            "/api/v1/units/{$structure['unit']->id}/invitations",
            [
                'user_ids' => [$invitee->id],
                'relation_type' => OccupancyType::FamilyMember->value,
                'channel' => InvitationChannel::Sms->value,
                'message' => 'دعوت عضو خانواده',
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

        $this->assertDatabaseMissing(
            'unit_occupancies',
            [
                'unit_id' => $structure['unit']->id,
                'user_id' => $invitee->id,
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
