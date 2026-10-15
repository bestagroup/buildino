<?php

namespace Tests\Feature\Web;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Database\Seeders\RoleMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('buildino-building-setup-engine.js', false)
            ->assertViewHas('setupConfig', fn (array $config) => $config['canCreateComplex'] && $config['canCreateBuilding'] && count($config['locations']) === 31);
        $this->get('/management')->assertOk()->assertSee(route('management.building-setup'), false);
    }

    public function test_building_manager_only_receives_its_own_building(): void
    {
        $owned = $this->createBuildingGraph();
        $other = $this->createBuildingGraph();
        $user = $this->assigned('building_manager', $owned['building']);
        $this->actingAs($user)->get('/management/building-setup?building_id='.$other['building']->id)->assertOk()
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
