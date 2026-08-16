<?php

namespace Tests\Feature\Security;

use App\Models\BuildingExpense;
use App\Models\DocumentRecord;
use App\Models\MeetingMinute;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class ScopedOperationalResourcesTest extends TestCase
{
    use CreatesBuildingDomainData, RefreshDatabase;

    public function test_financial_collections_and_creation_are_building_scoped(): void
    {
        $allowed = $this->createBuildingGraph();
        $outside = $this->createBuildingGraph();
        $manager = $this->verifiedUser();

        $this->grant(
            $manager,
            $allowed['building'],
            [
                'expenses.view',
                'expenses.create',
                'expenses.update',
                'incomes.view',
                'incomes.create',
                'incomes.update',
            ]
        );

        $allowedExpense = BuildingExpense::query()->create([
            'building_id' => $allowed['building']->id,
            'title' => 'Allowed expense',
            'amount' => 1000,
            'expense_date' => '2026-08-16',
            'created_by' => $manager->id,
        ]);

        BuildingExpense::query()->create([
            'building_id' => $outside['building']->id,
            'title' => 'Outside expense',
            'amount' => 2000,
            'expense_date' => '2026-08-16',
        ]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/expenses')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $allowedExpense->id);

        $this->postJson('/api/v1/expenses', [
            'building_id' => $outside['building']->id,
            'title' => 'Forbidden creation',
            'amount' => 3000,
            'expense_date' => '2026-08-16',
        ])->assertForbidden();

        $created = $this->postJson('/api/v1/incomes', [
            'building_id' => $allowed['building']->id,
            'title' => 'Allowed income',
            'amount' => 5000,
            'income_date' => '2026-08-16',
        ])
            ->assertCreated()
            ->assertJsonPath('data.created_by', $manager->id);

        $this->patchJson(
            '/api/v1/incomes/'.$created->json('data.id'),
            ['building_id' => $outside['building']->id]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('building_id');
    }

    public function test_document_target_alias_and_scope_are_enforced(): void
    {
        $allowed = $this->createBuildingGraph();
        $outside = $this->createBuildingGraph();
        $manager = $this->verifiedUser();

        $this->grant(
            $manager,
            $allowed['building'],
            [
                'documents.view',
                'documents.create',
                'documents.update',
            ]
        );

        Sanctum::actingAs($manager);

        $document = $this->postJson('/api/v1/documents', [
            'documentable_type' => 'unit',
            'documentable_id' => $allowed['unit']->id,
            'title' => 'Unit deed',
            'document_type' => 'ownership',
            'document_date' => '2026-08-16',
        ])
            ->assertCreated()
            ->assertJsonPath('data.created_by', $manager->id);

        $this->assertSame(
            $allowed['unit']->getMorphClass(),
            $document->json('data.documentable_type')
        );

        $documentId = $document->json('data.id');

        $this->patchJson(
            "/api/v1/documents/{$documentId}",
            ['title' => 'Updated unit deed']
        )
            ->assertOk()
            ->assertJsonPath(
                'data.title',
                'Updated unit deed'
            );

        $this->patchJson(
            "/api/v1/documents/{$documentId}",
            [
                'documentable_type' => 'building',
                'documentable_id' => $outside['building']->id,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'documentable_type',
                'documentable_id',
            ]);

        $this->postJson('/api/v1/documents', [
            'documentable_type' => 'building',
            'documentable_id' => $outside['building']->id,
            'title' => 'Outside document',
            'document_type' => 'building',
        ])->assertForbidden();

        $this->postJson('/api/v1/documents', [
            'documentable_type' => User::class,
            'documentable_id' => $manager->id,
            'title' => 'Arbitrary morph target',
            'document_type' => 'other',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('documentable_type');

        DocumentRecord::query()->create([
            'documentable_type' =>
                $outside['building']->getMorphClass(),
            'documentable_id' => $outside['building']->id,
            'title' => 'Hidden document',
            'document_type' => 'building',
        ]);

        $this->getJson('/api/v1/documents')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_meeting_minutes_are_scoped_and_actor_is_server_owned(): void
    {
        $allowed = $this->createBuildingGraph();
        $outside = $this->createBuildingGraph();
        $manager = $this->verifiedUser();
        $otherUser = $this->verifiedUser();

        $this->grant(
            $manager,
            $allowed['building'],
            [
                'meeting-minutes.view',
                'meeting-minutes.create',
                'meeting-minutes.update',
            ]
        );

        MeetingMinute::query()->create([
            'building_id' => $outside['building']->id,
            'title' => 'Hidden minute',
            'meeting_at' => '2026-08-16 10:00:00',
        ]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/meeting-minutes', [
            'building_id' => $allowed['building']->id,
            'title' => 'Client-owned actor attempt',
            'meeting_at' => '2026-08-16 12:00:00',
            'created_by' => $otherUser->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('created_by');

        $created = $this->postJson('/api/v1/meeting-minutes', [
            'building_id' => $allowed['building']->id,
            'title' => 'Allowed minute',
            'meeting_at' => '2026-08-16 12:00:00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.created_by', $manager->id);

        $this->getJson('/api/v1/meeting-minutes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $created->json('data.id')
            );

        $this->patchJson(
            '/api/v1/meeting-minutes/'.$created->json('data.id'),
            ['building_id' => $outside['building']->id]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('building_id');
    }

    private function verifiedUser(): User
    {
        return $this->createUser([
            'mobile_verified_at' => now(),
        ]);
    }

    /**
     * @param array<int, string> $permissionNames
     */
    private function grant(
        User $user,
        mixed $scope,
        array $permissionNames
    ): void {
        $role = Role::query()->create([
            'name' => 'scoped-'.uniqid(),
            'display_name' => 'Scoped test role',
            'is_system' => false,
        ]);

        foreach ($permissionNames as $permissionName) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $permissionName],
                [
                    'display_name' => $permissionName,
                    'module' => explode('.', $permissionName)[0],
                ]
            );

            $role->permissions()->syncWithoutDetaching([
                $permission->id,
            ]);
        }

        UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => $scope->getMorphClass(),
            'scope_id' => $scope->getKey(),
            'starts_at' => now()->subMinute(),
            'is_active' => true,
        ]);
    }
}
