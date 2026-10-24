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

    public function test_candidate_lookup_requires_occupancy_create_scope_and_exact_unlinked_contact(): void
    {
        $own = $this->createBuildingGraph();
        $other = $this->createBuildingGraph();
        $candidate = $this->createUser(['mobile' => '09129990011']);
        $this->createUser(['mobile' => '09129990012', 'is_blocked' => true]);
        $this->actingAs($this->assigned('building_manager', $own['building']))
            ->getJson('/management/lookups/occupancy_candidates?unit_id='.$own['unit']->id.'&search=09129990011')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $candidate->id);
        $this->getJson('/management/lookups/occupancy_candidates?unit_id='.$own['unit']->id.'&search=0912999001')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/management/lookups/occupancy_candidates?unit_id='.$own['unit']->id.'&search=09129990012')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/management/lookups/occupancy_candidates?unit_id='.$other['unit']->id.'&search=09129990011')
            ->assertForbidden();
        $this->actingAs($this->assigned('support_agent', $own['building']))
            ->getJson('/management/lookups/occupancy_candidates?unit_id='.$own['unit']->id.'&search=09129990011')
            ->assertForbidden();
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
