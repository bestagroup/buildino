<?php

namespace Tests\Feature\Security;

use App\Enums\InvitationChannel;
use App\Enums\InvitationStatus;
use App\Enums\OccupancyType;
use App\Enums\UnitUsageType;
use App\Models\Block;
use App\Models\Building;
use App\Models\Complex;
use App\Models\Floor;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitInvitation;
use App\Models\UnitOccupancy;
use App\Models\UnitOwnership;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnitInvitationFlowTest extends TestCase
{
    use RefreshDatabase;

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

        Sanctum::actingAs($manager);

        $response = $this->postJson(
            "/api/v1/units/{$structureA['unit']->id}/invitations",
            [
                'mobile' => $invitee->mobile,
                'relation_type' => OccupancyType::Tenant->value,
                'channel' => InvitationChannel::Sms->value,
                'expires_in_hours' => 72,

                // Must be ignored because unit comes from route.
                'unit_id' => $structureB['unit']->id,
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
                InvitationStatus::Sent->value
            );

        $this->assertNotEmpty(
            $response->json('meta.accept_token')
        );

        $this->postJson(
            "/api/v1/units/{$structureB['unit']->id}/invitations",
            [
                'mobile' => $invitee->mobile,
                'relation_type' => OccupancyType::Tenant->value,
                'channel' => InvitationChannel::Sms->value,
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
                'unit-invitations.create',
            ]
        );

        $this->assignRole(
            $manager,
            $role,
            $structure['building']
        );

        Sanctum::actingAs($manager);

        $create = $this->postJson(
            "/api/v1/units/{$structure['unit']->id}/invitations",
            [
                'mobile' => $invitee->mobile,
                'relation_type' => OccupancyType::Tenant->value,
                'channel' => InvitationChannel::Sms->value,
            ]
        )->assertCreated();

        $token = $create->json(
            'meta.accept_token'
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
                'unit-invitations.create',
            ]
        );

        $this->assignRole(
            $manager,
            $role,
            $structure['building']
        );

        Sanctum::actingAs($manager);

        $create = $this->postJson(
            "/api/v1/units/{$structure['unit']->id}/invitations",
            [
                'mobile' => $invitee->mobile,
                'relation_type' => OccupancyType::Resident->value,
                'channel' => InvitationChannel::Sms->value,
            ]
        )->assertCreated();

        $token = $create->json(
            'meta.accept_token'
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
                'unit-invitations.create',
                'unit-invitations.update',
            ]
        );

        $this->assignRole(
            $manager,
            $role,
            $structure['building']
        );

        Sanctum::actingAs($manager);

        $create = $this->postJson(
            "/api/v1/units/{$structure['unit']->id}/invitations",
            [
                'mobile' => $invitee->mobile,
                'relation_type' => OccupancyType::FamilyMember->value,
                'channel' => InvitationChannel::Sms->value,
            ]
        )->assertCreated();

        $invitationId = $create->json(
            'data.id'
        );

        $oldToken = $create->json(
            'meta.accept_token'
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
