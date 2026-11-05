<?php

namespace Tests\Feature\Web;

use App\Models\Role;
use App\Models\Building;
use App\Models\BuildingComplexRequest;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Database\Seeders\RoleMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

final class BuildingSetupWebTest extends TestCase
{
    use CreatesBuildingDomainData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleMatrixSeeder::class);
    }

    public function test_same_host_management_api_request_is_treated_as_stateful(): void
    {
        $request = Request::create(
            'https://panel.buildino.ir/api/v1/buildings/1/blocks',
            'GET',
            [],
            [],
            [],
            [
                'HTTP_REFERER' =>
                    'https://panel.buildino.ir/management/building-setup',
            ]
        );

        $this->assertTrue(
            EnsureFrontendRequestsAreStateful::fromFrontend($request)
        );
    }

    public function test_guest_cannot_open_building_setup(): void
    {
        $this->get('/management/building-setup')->assertRedirect(route('login'));
    }

    public function test_global_manager_sees_the_single_page_setup_and_dashboard_entry(): void
    {
        $user = $this->assigned('superadmin');
        $this->actingAs($user)->get('/management/building-setup')->assertOk()
            ->assertSee('data-building-setup', false)
            ->assertSee('data-setup-step="3"', false)
            ->assertSee('data-setup-ownership', false)
            ->assertSee('buildino-building-setup-engine.js', false)
            ->assertViewHas('setupConfig', fn (array $config) => $config['canCreateComplex'] && $config['canCreateBuilding']
                && $config['canManageOwnership'] && count($config['locations']) === 31);
        $this->get('/management')->assertOk()->assertSee(route('management.building-setup'), false);
    }

    public function test_building_manager_only_receives_its_own_building(): void
    {
        $owned = $this->createBuildingGraph();
        $other = $this->createBuildingGraph();
        $user = $this->assigned('building_manager', $owned['building']);
        $this->actingAs($user)->get('/management/building-setup?building_id='.$other['building']->id)->assertOk()
            ->assertSee('data-setup-selected-building', false)
            ->assertSee($owned['building']->title)
            ->assertViewHas('setupConfig', function (array $config) use ($owned, $other): bool {
                $ids = $config['buildings']->pluck('id')->all();

                return in_array($owned['building']->id, $ids, true)
                    && ! in_array($other['building']->id, $ids, true)
                    && ! $config['canCreateComplex'];
            });
    }

    public function test_finance_manager_cannot_open_the_setup_or_see_its_navigation_entry(): void
    {
        $structure = $this->createBuildingGraph();
        $user = $this->assigned('finance_manager', $structure['building']);
        $this->actingAs($user)->get('/management/building-setup')->assertForbidden();
        $this->get('/management')->assertOk()->assertDontSee('data-nav-key="building-setup"', false);
    }

    public function test_new_workspace_remains_incomplete_until_final_confirmation_and_legacy_units_are_preserved(): void
    {
        $graph = $this->createBuildingGraph();
        $graph['building']->update(['structure_type' => 'standalone']);
        $user = $this->assigned('building_manager', $graph['building']);
        $this->actingAs($user)->get('/management')->assertSee('ادامه راه‌اندازی');
        $this->postJson('/management/building-setup/'.$graph['building']->id.'/complete')->assertOk();
        $this->assertNotNull($graph['building']->refresh()->setup_completed_at);
        $this->assertSame(1, $graph['building']->units_count);
        $this->get('/management')->assertDontSee('ادامه راه‌اندازی');
        $this->assertDatabaseHas('units', ['id' => $graph['unit']->id]);
        $other = $this->createBuildingGraph();
        $this->postJson('/management/building-setup/'.$other['building']->id.'/complete')->assertForbidden();
    }

    public function test_completed_setup_reopens_at_next_actions_after_return_or_reload(): void
    {
        $graph = $this->createBuildingGraph();
        $graph['building']->update(['structure_type' => 'standalone']);
        $user = $this->assigned('building_manager', $graph['building']);
        $this->actingAs($user)->postJson('/management/building-setup/'.$graph['building']->id.'/complete')->assertOk();
        foreach (['/management/building-setup?building_id='.$graph['building']->id, '/management/building-setup'] as $url) {
            $this->get($url)->assertOk()->assertViewHas('setupConfig', fn ($config) => $config['completedSetup']['buildingId'] === $graph['building']->id
                && $config['completedSetup']['firstUnitId'] === $graph['unit']->id
                && $config['completedSetup']['floors'] === 1 && $config['completedSetup']['units'] === 1
                && $config['completedSetup']['canManageOwnership'] && $config['completedSetup']['canInvite'])
                ->assertSee('data-setup-form novalidate  hidden', false);
        }
        $this->assertDatabaseCount('units', 1);
        $this->assertDatabaseCount('floors', 1);
    }

    public function test_completion_state_does_not_leak_other_buildings_or_override_incomplete_work(): void
    {
        $own = $this->createBuildingGraph();
        $other = $this->createBuildingGraph();
        $other['building']->forceFill(['setup_completed_at' => now()])->save();
        $own['building']->update(['structure_type' => 'standalone']);
        $this->actingAs($this->assigned('building_manager', $own['building']));
        $this->get('/management/building-setup?building_id='.$other['building']->id)->assertOk()
            ->assertViewHas('setupConfig', fn ($config) => $config['completedSetup'] === null);
        $this->get('/management/building-setup')->assertOk()
            ->assertViewHas('setupConfig', fn ($config) => $config['completedSetup'] === null && $config['initialBuildingId'] === $own['building']->id);
    }

    public function test_explicit_new_setup_remains_available_after_existing_building_is_completed(): void
    {
        $graph = $this->createBuildingGraph();
        $graph['building']->forceFill(['setup_completed_at' => now()])->save();
        $this->actingAs($this->assigned('complex_manager', $graph['complex']))->get('/management/building-setup?new=1')->assertOk()
            ->assertViewHas('setupConfig', fn ($config) => $config['completedSetup'] === null && $config['startNew'] && $config['initialBuildingId'] === 0);
        $this->get('/management/building-setup?complex_id='.$graph['complex']->id)->assertOk()
            ->assertViewHas('setupConfig', fn ($config) => $config['completedSetup'] === null && $config['initialBuildingId'] === 0);
    }

    public function test_empty_structure_cannot_be_marked_complete(): void
    {
        $graph = $this->createBuildingGraph();
        $graph['unit']->delete();
        $user = $this->assigned('building_manager', $graph['building']);
        $this->actingAs($user)->postJson('/management/building-setup/'.$graph['building']->id.'/complete')->assertUnprocessable();
        $this->assertNull($graph['building']->refresh()->setup_completed_at);
    }

    public function test_complex_connection_requires_approval_and_never_grants_sibling_or_complex_access(): void
    {
        $source = $this->createBuildingGraph();
        $source['complex']->update(['workspace_type' => 'unlinked']);
        $source['building']->update(['structure_type' => 'complex_block']);
        $target = $this->createBuildingGraph();
        $target['complex']->update(['workspace_type' => 'complex']);
        $manager = $this->assigned('building_manager', $source['building']);
        $complexManager = $this->assigned('complex_manager', $target['complex']);
        $stranger = $this->assigned('building_manager', $target['building']);
        $url = '/management/building-setup/'.$source['building']->id.'/connect';
        $this->actingAs($manager)->post($url, ['complex_code' => $target['complex']->code])->assertRedirect();
        $connection = BuildingComplexRequest::query()->firstOrFail();
        $this->assertSame($source['complex']->id, $source['building']->refresh()->complex_id);
        $this->post($url, ['complex_code' => $target['complex']->code])->assertSessionHasErrors('complex_code');
        $review = '/management/building-setup/connections/'.$connection->id;
        $this->post($review, ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($stranger)->post($review, ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($complexManager)->get('/management/building-setup')->assertOk()->assertSee('تأیید اتصال');
        $this->post($review, ['decision' => 'approved'])->assertRedirect();
        $this->assertSame($target['complex']->id, $source['building']->refresh()->complex_id);
        $this->post($review, ['decision' => 'approved'])->assertConflict();
        $permissions = app(\App\Support\Authorization\PermissionChecker::class);
        $this->assertTrue($permissions->allows($manager, 'units.create', $source['building']));
        $this->assertFalse($permissions->allows($manager, 'units.create', $target['building']));
        $this->assertFalse($permissions->allows($manager, 'complexes.update', $target['complex']));
        $this->assertTrue($permissions->allows($complexManager, 'units.create', $source['building']));
    }

    public function test_only_block_manager_can_cancel_own_pending_connection_and_retry(): void
    {
        $source = $this->createBuildingGraph();
        $source['complex']->update(['workspace_type' => 'unlinked']);
        $source['building']->update(['structure_type' => 'complex_block']);
        $target = $this->createBuildingGraph();
        $target['complex']->update(['workspace_type' => 'complex']);
        $manager = $this->assigned('building_manager', $source['building']);
        $reviewer = $this->assigned('complex_manager', $target['complex']);
        $url = '/management/building-setup/'.$source['building']->id.'/connect';
        $this->actingAs($manager)->post($url, ['complex_code' => 'not-found'])->assertSessionHasErrors('complex_code');
        $this->assertDatabaseCount('building_complex_requests', 0);
        $this->post($url, ['complex_code' => $target['complex']->code]);
        $review = '/management/building-setup/connections/'.BuildingComplexRequest::query()->value('id');
        $this->actingAs($reviewer)->post($review, ['decision' => 'cancelled'])->assertForbidden();
        $this->actingAs($manager)->post($review, ['decision' => 'cancelled'])->assertRedirect();
        $this->post($url, ['complex_code' => $target['complex']->code])->assertRedirect();
        $this->assertDatabaseCount('building_complex_requests', 2);
        $this->actingAs($reviewer)->post('/management/building-setup/connections/'.BuildingComplexRequest::query()->max('id'), ['decision' => 'rejected'])->assertRedirect();
        $this->assertSame($source['complex']->id, $source['building']->refresh()->complex_id);
    }

    public function test_api_creates_complex_blocks_at_building_scope_and_keeps_legacy_callers_compatible(): void
    {
        $graph = $this->createBuildingGraph();
        $graph['complex']->update(['workspace_type' => 'complex']);
        $manager = $this->assigned('complex_manager', $graph['complex']);
        $this->actingAs($manager)->postJson('/api/v1/buildings', ['complex_id' => $graph['complex']->id, 'title' => 'بلوک دوم', 'code' => 'NEW-BLOCK'])
            ->assertCreated()->assertJsonPath('data.structure_type', 'complex_block');
        $foreign = $this->createBuildingGraph();
        $this->patchJson('/api/v1/buildings/'.$graph['building']->id, ['complex_id' => $foreign['complex']->id])->assertForbidden();
    }

    public function test_single_building_container_cannot_gain_a_second_building_and_unlinked_is_not_a_public_type(): void
    {
        $graph = $this->createBuildingGraph();
        $graph['complex']->update(['workspace_type' => 'standalone']);
        $manager = $this->assigned('superadmin');
        $this->actingAs($manager)->postJson('/api/v1/buildings', ['complex_id' => $graph['complex']->id, 'title' => 'Extra', 'code' => 'EXTRA'])
            ->assertUnprocessable();
        $this->postJson('/api/v1/complexes', ['code' => 'PRIVATE', 'title' => 'Private', 'province' => 'Tehran', 'city' => 'Tehran', 'workspace_type' => 'unlinked'])
            ->assertUnprocessable();
        $this->postJson('/api/v1/complexes', ['code' => 'PUBLIC', 'title' => 'Public', 'province' => 'Tehran', 'city' => 'Tehran', 'workspace_type' => 'complex'])
            ->assertCreated()->assertJsonPath('data.workspace_type', 'complex');
        $legacy = $this->createBuildingGraph();
        $this->postJson('/api/v1/buildings', ['complex_id' => $legacy['complex']->id, 'title' => 'New block', 'code' => 'OPT-IN', 'structure_type' => 'complex_block'])
            ->assertCreated()->assertJsonPath('data.structure_type', 'complex_block');
        $this->postJson('/api/v1/buildings', ['complex_id' => $legacy['complex']->id, 'title' => 'Legacy', 'code' => 'LEGACY'])
            ->assertCreated()->assertJsonPath('data.structure_type', null);
    }

    private function assigned(string $role, $scope = null): User
    {
        $user = $this->createUser();
        UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => Role::query()->where('name', $role)->value('id'),
            'scope_type' => $scope?->getMorphClass(),
            'scope_id' => $scope?->id,
            'is_active' => true,
        ]);

        return $user;
    }
}
