<?php

namespace Tests\Feature\System;

use App\Enums\OccupancyType;
use App\Enums\SupportTicketStatus;
use App\Models\NotificationLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SupportSlaPolicy;
use App\Models\UnitOccupancy;
use App\Models\UserRoleAssignment;
use App\Services\Notifications\UserNotificationService;
use App\Data\Notifications\NotificationMessage;
use App\Services\System\FinalIntegrityAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class FinalCompletionFlowTest extends TestCase
{
    use RefreshDatabase, CreatesBuildingDomainData;

    public function test_support_ticket_uses_server_owned_identity_number_and_sla_workflow(): void
    {
        $graph = $this->createBuildingGraph();
        $resident = $this->createUser([
            'mobile_verified_at' => now(),
        ]);
        $manager = $this->createUser([
            'mobile_verified_at' => now(),
        ]);
        $assignee = $this->createUser();

        $this->occupy($resident, $graph['unit']);

        SupportSlaPolicy::query()->create([
            'support_category_id' => null,
            'priority' => 'medium',
            'first_response_minutes' => 30,
            'resolution_minutes' => 240,
            'is_active' => true,
        ]);

        $this->grantBuildingPermissions(
            $manager,
            $graph['building'],
            [
                'support-tickets.view',
                'support-tickets.update',
            ]
        );

        Sanctum::actingAs($resident);

        $created = $this->postJson(
            '/api/v1/support-tickets',
            [
                'building_id' => $graph['building']->id,
                'unit_id' => $graph['unit']->id,
                'subject' => 'Elevator problem',
                'description' => 'Elevator is unavailable.',
                'priority' => 'medium',
                // hostile/client-owned workflow fields: ignored by FormRequest
                'user_id' => $manager->id,
                'ticket_number' => 'CLIENT-NUMBER',
                'status' => 'closed',
                'assigned_to' => $manager->id,
            ]
        );

        $created
            ->assertCreated()
            ->assertJsonPath('data.user_id', $resident->id)
            ->assertJsonPath('data.status', 'open');

        $ticketId = (int) $created->json('data.id');

        $this->assertNotSame(
            'CLIENT-NUMBER',
            $created->json('data.ticket_number')
        );

        $this->assertNotNull(
            $created->json('data.response_due_at')
        );

        $this->assertNotNull(
            $created->json('data.resolution_due_at')
        );

        Sanctum::actingAs($manager);

        $this->postJson(
            "/api/v1/support-tickets/{$ticketId}/assign",
            ['assigned_to' => $assignee->id]
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.assigned_to', $assignee->id);

        $this->postJson(
            "/api/v1/support-tickets/{$ticketId}/messages",
            [
                'message' => 'We are investigating.',
                'is_internal' => false,
            ]
        )->assertCreated();

        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticketId,
            'status' => SupportTicketStatus::InProgress->value,
        ]);

        $this->assertDatabaseMissing('support_tickets', [
            'id' => $ticketId,
            'first_response_at' => null,
        ]);

        $this->postJson(
            "/api/v1/support-tickets/{$ticketId}/resolve",
            ['resolution' => 'Elevator reset completed.']
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');

        $this->postJson(
            "/api/v1/support-tickets/{$ticketId}/close"
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        Sanctum::actingAs($resident);

        $this->postJson(
            "/api/v1/support-tickets/{$ticketId}/reopen"
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'assigned');
    }

    public function test_notification_inbox_device_preferences_and_database_delivery_work_end_to_end(): void
    {
        $user = $this->createUser([
            'mobile_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $deviceResponse = $this->postJson(
            '/api/v1/notification-devices',
            [
                'device_id' => 'device-final-1',
                'platform' => 'android',
                'device_name' => 'Test Phone',
                'push_token' => 'push-token-final-1',
            ]
        );

        $this->assertSame(
            201,
            $deviceResponse->status(),
            $deviceResponse->getContent()
        );

        $deviceResponse
            ->assertJsonPath('data.device_id', 'device-final-1');

        $this->putJson(
            '/api/v1/notification-preferences',
            [
                'preferences' => [
                    [
                        'notification_type' => 'support.message',
                        'channel' => 'push',
                        'is_enabled' => true,
                    ],
                    [
                        'notification_type' => 'support.message',
                        'channel' => 'sms',
                        'is_enabled' => false,
                    ],
                ],
            ]
        )->assertOk();

        app(UserNotificationService::class)->send(
            $user,
            new NotificationMessage(
                'test.database',
                'Database notification',
                'Hello from Buildino'
            ),
            'database',
            'final-database-notification'
        );

        $log = NotificationLog::query()
            ->where('idempotency_key', 'final-database-notification')
            ->firstOrFail();

        $this->getJson('/api/v1/notifications')
            ->assertOk();

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $this->postJson(
            "/api/v1/notifications/{$log->id}/read"
        )
            ->assertOk();

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_service_request_rejects_client_owned_workflow_fields_and_manager_assigns_provider(): void
    {
        $graph = $this->createBuildingGraph();
        $resident = $this->createUser([
            'mobile_verified_at' => now(),
        ]);
        $manager = $this->createUser([
            'mobile_verified_at' => now(),
        ]);
        $provider = $this->createUser();
        $other = $this->createUser();

        $this->occupy($resident, $graph['unit']);

        $this->grantBuildingPermissions(
            $manager,
            $graph['building'],
            [
                'service-requests.view',
                'service-requests.update',
            ]
        );

        Sanctum::actingAs($resident);

        $created = $this->postJson(
            '/api/v1/service-requests',
            [
                'building_id' => $graph['building']->id,
                'unit_id' => $graph['unit']->id,
                'type' => 'electrical',
                'priority' => 'normal',
                'title' => 'Electrical repair',
                'description' => 'Socket problem',
                'requested_by' => $other->id,
                'status' => 'completed',
                'assigned_to' => $other->id,
            ]
        );

        $created
            ->assertCreated()
            ->assertJsonPath('data.requested_by', $resident->id)
            ->assertJsonPath('data.status', 'open');

        $requestId = (int) $created->json('data.id');

        $this->assertNull(
            $created->json('data.assigned_to')
        );

        $this->assertNotEmpty(
            $created->json('data.request_number')
        );

        Sanctum::actingAs($manager);

        $assignResponse = $this->postJson(
            "/api/v1/service-requests/{$requestId}/assign",
            ['assigned_to' => $provider->id]
        );

        $this->assertSame(
            200,
            $assignResponse->status(),
            $assignResponse->getContent()
        );

        $assignResponse
            ->assertJsonPath('data.assigned_to', $provider->id)
            ->assertJsonPath('data.status', 'assigned');
    }

    public function test_clean_database_passes_final_integrity_audit(): void
    {
        $result = app(
            FinalIntegrityAuditService::class
        )->inspect();

        $this->assertTrue(
            $result['ok'],
            json_encode($result, JSON_PRETTY_PRINT)
        );

        $this->assertSame(0, $result['critical_count']);
    }

    private function occupy($user, $unit): UnitOccupancy
    {
        return UnitOccupancy::query()->create([
            'unit_id' => $unit->id,
            'user_id' => $user->id,
            'occupancy_type' => OccupancyType::Resident,
            'starts_at' => now()->toDateString(),
            'ends_at' => null,
            'is_primary' => true,
            'is_active' => true,
        ]);
    }

    private function grantBuildingPermissions(
        $user,
        $building,
        array $permissionNames
    ): void {
        $role = Role::query()->create([
            'name' => 'final-role-'.uniqid(),
            'display_name' => 'Final Role',
            'is_system' => false,
        ]);

        $permissionIds = [];

        foreach ($permissionNames as $name) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $name],
                [
                    'display_name' => $name,
                    'module' => 'final',
                ]
            );

            $permissionIds[] = $permission->id;
        }

        $role->permissions()->sync($permissionIds);

        UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => $building->getMorphClass(),
            'scope_id' => $building->id,
            'starts_at' => now()->subMinute(),
            'ends_at' => null,
            'is_active' => true,
            'assigned_by' => null,
        ]);
    }
}
