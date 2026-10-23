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

    public function test_owner_search_finds_unlinked_active_account_only_inside_create_scope(): void
    {
        $own = $this->createBuildingGraph();
        $foreign = $this->createBuildingGraph();
        $candidate = $this->createUser(['mobile' => '09129990011']);
        $this->createUser(['mobile' => '09129990012', 'is_blocked' => true]);
        $manager = $this->assigned('building_manager', $own['building']);

        $this->actingAs($manager)
            ->getJson('/management/lookups/ownership_candidates?unit_id='.$own['unit']->id.'&search=09129990011')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $candidate->id);

        $this->getJson('/management/lookups/ownership_candidates?unit_id='.$own['unit']->id.'&search=0912999001')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/management/lookups/ownership_candidates?unit_id='.$own['unit']->id.'&search=09129990012')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/management/lookups/ownership_candidates?unit_id='.$own['unit']->id.'&search=0')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/management/lookups/ownership_candidates?unit_id='.$foreign['unit']->id.'&search=0912999001')
            ->assertForbidden();

        $viewer = $this->assigned('support_agent', $own['building']);
        $this->actingAs($viewer)
            ->getJson('/management/lookups/ownership_candidates?unit_id='.$own['unit']->id.'&search=0912999001')
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
