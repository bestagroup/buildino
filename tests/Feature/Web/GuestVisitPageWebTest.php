<?php

namespace Tests\Feature\Web;

use App\Models\Permission;
use App\Models\Role;
use App\Models\UserRoleAssignment;
use Database\Seeders\RoleMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

final class GuestVisitPageWebTest extends TestCase
{
    use CreatesBuildingDomainData;
    use RefreshDatabase;

    public function test_guest_page_requires_login(): void
    {
        $this->get('/management/operations/guest-visits')->assertRedirect(route('login'));
    }

    public function test_page_limits_building_context_and_write_capabilities_to_assigned_scope(): void
    {
        $this->seed(RoleMatrixSeeder::class);
        $own = $this->createBuildingGraph();
        $other = $this->createBuildingGraph();
        $own['building']->update(['title' => 'ساختمان مجاز مهمان']);
        $other['building']->update(['title' => 'ساختمان خارج از محدوده']);
        $user = $this->createUser();
        $role = Role::query()->where('name', 'building_manager')->firstOrFail();
        UserRoleAssignment::query()->create([
            'user_id' => $user->id, 'role_id' => $role->id,
            'scope_type' => $own['building']->getMorphClass(), 'scope_id' => $own['building']->id, 'is_active' => true,
        ]);

        $this->actingAs($user)->get('/management/operations/guest-visits')
            ->assertOk()->assertViewIs('management.guest-visits')
            ->assertSee('data-guest-page', false)
            ->assertSee('ساختمان مجاز مهمان')->assertDontSee('ساختمان خارج از محدوده')
            ->assertViewHas('guestConfig', fn (array $config): bool => $config['buildings']->count() === 1
                && $config['buildings']->first()['canCreate'] && $config['buildings']->first()['canUpdate']);

        // Keep dashboard access but remove guest mutation permissions.
        $role->permissions()->detach(Permission::query()->whereIn('name', ['guest-visits.create', 'guest-visits.update'])->pluck('id'));
        $this->actingAs($user->fresh())->get('/management/operations/guest-visits')
            ->assertOk()->assertViewHas('guestConfig', fn (array $config): bool => ! $config['buildings']->first()['canCreate']
                && ! $config['buildings']->first()['canUpdate']);
    }
}
