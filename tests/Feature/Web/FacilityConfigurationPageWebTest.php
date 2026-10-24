<?php

namespace Tests\Feature\Web;

use App\Models\Role;
use App\Models\UserRoleAssignment;
use Database\Seeders\RoleMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

final class FacilityConfigurationPageWebTest extends TestCase
{
    use CreatesBuildingDomainData;
    use RefreshDatabase;

    public function test_configuration_requires_login(): void
    {
        $this->get('/management/operations/facilities')->assertRedirect(route('login'));
    }

    public function test_manager_only_receives_assigned_building_and_viewer_has_no_write_capabilities(): void
    {
        $this->seed(RoleMatrixSeeder::class);
        $own = $this->createBuildingGraph();
        $other = $this->createBuildingGraph();
        $own['building']->update(['title' => 'ساختمان امکانات مجاز']);
        $other['building']->update(['title' => 'ساختمان خارج از محدوده امکانات']);

        foreach (['building_manager' => true, 'operator' => false] as $name => $canWrite) {
            $user = $this->createUser();
            UserRoleAssignment::query()->create([
                'user_id' => $user->id,
                'role_id' => Role::query()->where('name', $name)->value('id'),
                'scope_type' => $own['building']->getMorphClass(),
                'scope_id' => $own['building']->id, 'is_active' => true,
            ]);
            $this->actingAs($user)->get('/management/operations/facilities')
                ->assertOk()->assertViewIs('management.facility-configuration')
                ->assertSee('data-facility-page', false)
                ->assertSee('ساختمان امکانات مجاز')->assertDontSee('ساختمان خارج از محدوده امکانات')
                ->assertViewHas('facilityConfig', fn (array $config): bool => $config['buildings']->count() === 1
                    && $config['buildings']->first()['canCreate'] === $canWrite
                    && $config['buildings']->first()['canUpdate'] === $canWrite);
        }
    }
}
