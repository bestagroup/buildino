<?php

namespace Tests\Feature\Web;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Database\Seeders\RoleMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

final class UnitOwnershipPageWebTest extends TestCase
{
    use CreatesBuildingDomainData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleMatrixSeeder::class);
    }

    public function test_guest_cannot_open_the_ownership_page(): void
    {
        $this->get('/management/operations/ownerships')->assertRedirect(route('login'));
    }

    public function test_building_manager_only_receives_authorized_building_and_unit_context(): void
    {
        $own = $this->createBuildingGraph();
        $foreign = $this->createBuildingGraph();
        $own['building']->update(['title' => 'ساختمان مجاز']);
        $foreign['building']->update(['title' => 'ساختمان خارج از دسترس']);
        $user = $this->assigned('building_manager', $own['building']);

        $this->actingAs($user)
            ->get('/management/operations/ownerships?unit_id='.$foreign['unit']->id)
            ->assertOk()
            ->assertSee('data-unit-ownership-page', false)
            ->assertSee('ساختمان مجاز')
            ->assertDontSee('ساختمان خارج از دسترس')
            ->assertViewHas('ownershipConfig', function (array $config) use ($own, $foreign): bool {
                return $config['initialUnit'] === null
                    && $config['buildings']->pluck('id')->all() === [$own['building']->id]
                    && $config['buildings']->first()['canCreate']
                    && $config['buildings']->first()['canUpdate']
                    && ! $config['buildings']->contains('id', $foreign['building']->id);
            });

        $this->get('/management/operations/ownerships?unit_id='.$own['unit']->id)
            ->assertOk()
            ->assertViewHas('ownershipConfig', fn (array $config): bool => $config['initialUnit']['unitId'] === $own['unit']->id
                && $config['initialUnit']['buildingId'] === $own['building']->id);
    }

    public function test_view_only_role_does_not_receive_write_actions(): void
    {
        $structure = $this->createBuildingGraph();
        $user = $this->assigned('support_agent', $structure['building']);

        $this->actingAs($user)
            ->get('/management/operations/ownerships')
            ->assertOk()
            ->assertViewHas('ownershipConfig', fn (array $config): bool => $config['buildings']->count() === 1
                && ! $config['buildings']->first()['canCreate']
                && ! $config['buildings']->first()['canUpdate']);
    }

    public function test_role_without_ownership_permission_cannot_open_page(): void
    {
        $structure = $this->createBuildingGraph();
        $user = $this->assigned('finance_manager', $structure['building']);

        $this->actingAs($user)->get('/management/operations/ownerships')->assertForbidden();
    }

    public function test_owner_picker_lists_all_building_users_without_search_and_excludes_other_scopes(): void
    {
        $own = $this->createBuildingGraph();
        $foreign = $this->createBuildingGraph();
        $manager = $this->assigned('building_manager', $own['building']);
        // Even when the manager can access both buildings, the picker is unit-scoped.
        UserRoleAssignment::query()->create([
            'user_id' => $manager->id,
            'role_id' => Role::query()->where('name', 'building_manager')->value('id'),
            'scope_type' => $foreign['building']->getMorphClass(),
            'scope_id' => $foreign['building']->id,
            'is_active' => true,
        ]);
        $foreignUser = $this->assigned('owner', $foreign['building']);
        $unlinked = $this->createUser(['mobile' => '09129990011']);
        $blocked = $this->assigned('owner', $own['building']);
        $blocked->update(['is_blocked' => true]);
        $inactive = $this->assigned('owner', $own['building']);
        $inactive->update(['is_active' => false]);
        $expected = [$manager->id];
        for ($index = 0; $index < 35; $index++) {
            $candidate = $this->createUser();
            $candidate->managedUserScopes()->create([
                'scope_type' => $own['building']->getMorphClass(),
                'scope_id' => $own['building']->id,
                'assigned_by' => $manager->id,
            ]);
            $expected[] = $candidate->id;
        }

        $response = $this->actingAs($manager)
            ->getJson('/management/lookups/ownership_candidates?unit_id='.$own['unit']->id)
            ->assertOk()->assertJsonCount(36, 'data');
        $this->assertEqualsCanonicalizing($expected, array_column($response->json('data'), 'id'));
        $this->getJson('/management/lookups/ownership_candidates?unit_id='.$own['unit']->id.'&search='.$unlinked->mobile)
            ->assertOk()->assertJsonCount(0, 'data');
        $this->get('/management/operations/ownerships?unit_id='.$own['unit']->id)
            ->assertOk()->assertSee('id="ownership-user"', false)
            ->assertDontSee('data-ownership-user-search', false);
    }

    public function test_owner_picker_rejects_unauthorized_unit_and_view_only_role(): void
    {
        $own = $this->createBuildingGraph();
        $foreign = $this->createBuildingGraph();
        $manager = $this->assigned('building_manager', $own['building']);
        $this->actingAs($manager)
            ->getJson('/management/lookups/ownership_candidates?unit_id='.$foreign['unit']->id)
            ->assertForbidden();
        $viewer = $this->assigned('support_agent', $own['building']);
        $this->actingAs($viewer)
            ->getJson('/management/lookups/ownership_candidates?unit_id='.$own['unit']->id)
            ->assertForbidden();
    }

    public function test_manager_can_change_owner_but_cannot_select_a_foreign_or_duplicate_owner(): void
    {
        $own = $this->createBuildingGraph();
        $foreign = $this->createBuildingGraph();
        $manager = $this->assigned('building_manager', $own['building']);
        $old = $this->assigned('owner', $own['building']);
        $replacement = $this->assigned('owner', $own['building']);
        $outside = $this->assigned('owner', $foreign['building']);
        $ownership = \App\Models\UnitOwnership::query()->create([
            'unit_id' => $own['unit']->id, 'user_id' => $old->id,
            'starts_at' => '2026-01-01', 'is_active' => true,
            'is_primary' => true, 'ownership_percentage' => 50, 'created_by' => $manager->id,
        ]);
        \Laravel\Sanctum\Sanctum::actingAs($manager);
        $url = '/api/v1/unit-ownerships/'.$ownership->id;
        $this->patchJson($url, ['user_id' => $replacement->id])->assertOk();
        $this->assertDatabaseHas('unit_ownerships', [
            'id' => $ownership->id, 'user_id' => $replacement->id,
            'unit_id' => $own['unit']->id, 'is_primary' => true,
        ]);
        $this->patchJson($url, ['user_id' => $outside->id])->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $old->update(['is_blocked' => true]);
        $this->patchJson($url, ['user_id' => $old->id])->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $old->update(['is_blocked' => false]);
        \App\Models\UnitOwnership::query()->create([
            'unit_id' => $own['unit']->id, 'user_id' => $old->id,
            'starts_at' => '2026-01-01', 'is_active' => true, 'ownership_percentage' => 50,
        ]);
        $this->patchJson($url, ['user_id' => $old->id])->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->assertSame($replacement->id, $ownership->refresh()->user_id);
        \Laravel\Sanctum\Sanctum::actingAs($this->assigned('building_manager', $foreign['building']));
        $this->patchJson($url, ['user_id' => $outside->id])->assertForbidden();
    }

    private function assigned(string $role, $scope): User
    {
        $user = $this->createUser();
        UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => Role::query()->where('name', $role)->value('id'),
            'scope_type' => $scope->getMorphClass(),
            'scope_id' => $scope->id,
            'is_active' => true,
        ]);

        return $user;
    }
}
