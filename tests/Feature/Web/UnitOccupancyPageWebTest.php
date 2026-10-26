<?php

namespace Tests\Feature\Web;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Database\Seeders\RoleMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

final class UnitOccupancyPageWebTest extends TestCase
{
    use CreatesBuildingDomainData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleMatrixSeeder::class);
    }

    public function test_guest_cannot_open_page_and_viewer_receives_no_write_actions(): void
    {
        $this->get('/management/operations/occupancies')->assertRedirect(route('login'));
        $graph = $this->createBuildingGraph();
        $this->actingAs($this->assigned('support_agent', $graph['building']))
            ->get('/management/operations/occupancies')
            ->assertOk()
            ->assertViewHas('occupancyConfig', fn (array $config): bool => $config['buildings']->count() === 1
                && ! $config['buildings']->first()['canCreate']
                && ! $config['buildings']->first()['canUpdate']
                && ! $config['buildings']->first()['canInvite']);
    }

    public function test_page_only_exposes_authorized_building_and_unit(): void
    {
        $own = $this->createBuildingGraph();
        $other = $this->createBuildingGraph();
        $own['building']->update(['title' => 'ساختمان مجاز']);
        $other['building']->update(['title' => 'ساختمان نامجاز']);
        $this->actingAs($this->assigned('building_manager', $own['building']))
            ->get('/management/operations/occupancies?unit_id='.$other['unit']->id)
            ->assertOk()
            ->assertSee('data-unit-occupancy-page', false)
            ->assertSee('ساختمان مجاز')
            ->assertDontSee('ساختمان نامجاز')
            ->assertViewHas('occupancyConfig', fn (array $config): bool => $config['initialUnit'] === null
                && $config['buildings']->count() === 1
                && $config['buildings']->first()['canCreate']);

        $this->get('/management/operations/occupancies?unit_id='.$own['unit']->id)
            ->assertOk()
            ->assertViewHas('occupancyConfig', fn (array $config): bool => $config['initialUnit']['unitId'] === $own['unit']->id);
    }

    public function test_candidate_lookup_lists_all_active_building_users_without_search(): void
    {
        $own = $this->createBuildingGraph();
        $other = $this->createBuildingGraph();
        $manager = $this->assigned('building_manager', $own['building']);
        UserRoleAssignment::query()->create([
            'user_id' => $manager->id,
            'role_id' => Role::query()->where('name', 'building_manager')->value('id'),
            'scope_type' => $other['building']->getMorphClass(),
            'scope_id' => $other['building']->id,
            'is_active' => true,
        ]);
        $this->assigned('owner', $other['building']);
        $unlinked = $this->createUser(['mobile' => '09129990011']);
        $this->assigned('owner', $own['building'])->update(['is_blocked' => true]);
        $this->assigned('owner', $own['building'])->update(['is_active' => false]);
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
            ->getJson('/management/lookups/occupancy_candidates?unit_id='.$own['unit']->id)
            ->assertOk()->assertJsonCount(36, 'data');
        $this->assertEqualsCanonicalizing($expected, array_column($response->json('data'), 'id'));
        $this->getJson('/management/lookups/occupancy_candidates?unit_id='.$own['unit']->id.'&search='.$unlinked->mobile)
            ->assertOk()->assertJsonCount(0, 'data');
        $this->get('/management/operations/occupancies?unit_id='.$own['unit']->id)
            ->assertOk()->assertSee('id="occupancy-user"', false)
            ->assertDontSee('data-occupancy-user-search', false);
    }

    public function test_candidate_lookup_requires_create_permission_for_the_unit_building(): void
    {
        $own = $this->createBuildingGraph();
        $other = $this->createBuildingGraph();
        $this->actingAs($this->assigned('building_manager', $own['building']))
            ->getJson('/management/lookups/occupancy_candidates?unit_id='.$other['unit']->id)
            ->assertForbidden();
        $this->actingAs($this->assigned('support_agent', $own['building']))
            ->getJson('/management/lookups/occupancy_candidates?unit_id='.$own['unit']->id)
            ->assertForbidden();
    }


    public function test_owner_replacement_updates_occupancy_identity_without_transferring_residency(): void
    {
        $graph = $this->createBuildingGraph();
        $manager = $this->assigned('building_manager', $graph['building']);
        $old = $this->assigned('owner', $graph['building']);
        $replacement = $this->assigned('owner', $graph['building']);
        $ownership = $graph['unit']->unitOwnerships()->create([
            'user_id' => $old->id, 'is_active' => true,
            'starts_at' => now()->subDay()->toDateString(),
            'created_by' => $manager->id,
        ]);
        $occupancy = $graph['unit']->unitOccupancies()->create([
            'user_id' => $old->id, 'occupancy_type' => 'owner',
            'is_active' => true, 'starts_at' => now()->subDay()->toDateString(),
            'created_by' => $manager->id,
        ]);
        $this->actingAs($manager);
        $listUrl = '/api/v1/units/'.$graph['unit']->id.'/occupancies';
        $this->getJson($listUrl)->assertOk()->assertJsonPath('data.0.is_current_owner', true);
        $this->patchJson('/api/v1/unit-ownerships/'.$ownership->id, [
            'user_id' => $replacement->id,
        ])->assertOk();
        $this->getJson($listUrl)->assertOk()
            ->assertJsonPath('data.0.user_id', $old->id)
            ->assertJsonPath('data.0.is_current_owner', false);
        $candidates = collect($this->getJson('/management/lookups/occupancy_candidates?unit_id='.$graph['unit']->id)
            ->assertOk()->json('data'))->keyBy('id');
        $this->assertTrue($candidates[$replacement->id]['isCurrentOwner']);
        $this->assertFalse($candidates[$old->id]['isCurrentOwner']);
        $this->assertDatabaseHas('unit_occupancies', [
            'id' => $occupancy->id, 'user_id' => $old->id, 'is_active' => true,
        ]);

        $ownership->refresh()->update(['starts_at' => now()->addDay()->toDateString()]);
        $candidates = collect($this->getJson('/management/lookups/occupancy_candidates?unit_id='.$graph['unit']->id)
            ->assertOk()->json('data'))->keyBy('id');
        $this->assertFalse($candidates[$replacement->id]['isCurrentOwner']);
        $ownership->update(['starts_at' => now()->subDays(2)->toDateString(), 'ends_at' => now()->subDay()->toDateString()]);
        $candidates = collect($this->getJson('/management/lookups/occupancy_candidates?unit_id='.$graph['unit']->id)
            ->assertOk()->json('data'))->keyBy('id');
        $this->assertFalse($candidates[$replacement->id]['isCurrentOwner']);
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
