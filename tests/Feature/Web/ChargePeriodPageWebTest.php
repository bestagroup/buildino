<?php

namespace Tests\Feature\Web;

use App\Models\Permission;
use App\Models\Role;
use App\Models\UserRoleAssignment;
use Database\Seeders\RoleMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

final class ChargePeriodPageWebTest extends TestCase
{
    use CreatesBuildingDomainData;
    use RefreshDatabase;

    public function test_page_requires_login(): void
    {
        $this->get('/management/operations/charge-periods')->assertRedirect(route('login'));
    }

    public function test_page_uses_scoped_buildings_and_separate_operation_capabilities(): void
    {
        $this->seed(RoleMatrixSeeder::class);
        $own = $this->createBuildingGraph();
        $other = $this->createBuildingGraph();
        $own['building']->update(['title' => 'ساختمان دوره‌های مجاز']);
        $other['building']->update(['title' => 'ساختمان خارج از محدوده شارژ']);
        $user = $this->createUser();
        $role = Role::query()->where('name', 'building_manager')->firstOrFail();
        UserRoleAssignment::query()->create([
            'user_id' => $user->id, 'role_id' => $role->id,
            'scope_type' => $own['building']->getMorphClass(), 'scope_id' => $own['building']->id, 'is_active' => true,
        ]);
        $this->actingAs($user)->get('/management/operations/charge-periods')
            ->assertOk()->assertViewIs('management.charge-periods')
            ->assertSee('ساختمان دوره‌های مجاز')->assertDontSee('ساختمان خارج از محدوده شارژ')
            ->assertSee('buildino-charge-periods.js')->assertDontSee('buildino-crud.js')
            ->assertViewHas('periodConfig', fn (array $config): bool => $config['buildings']->count() === 1
                && $config['buildings']->first()['canCreate'] && $config['buildings']->first()['canUpdate']
                && $config['buildings']->first()['canCalculate'] && $config['buildings']->first()['canIssue']);

        $role->permissions()->detach(Permission::query()->whereIn('name', [
            'charge-periods.create', 'charge-periods.update', 'charge-periods.calculate', 'charge-periods.issue',
        ])->pluck('id'));
        $this->actingAs($user->fresh())->get('/management/operations/charge-periods')->assertOk()
            ->assertViewHas('periodConfig', fn (array $config): bool => ! $config['buildings']->first()['canCreate']
                && ! $config['buildings']->first()['canUpdate'] && ! $config['buildings']->first()['canCalculate']
                && ! $config['buildings']->first()['canIssue']);
    }

    public function test_authenticated_user_without_permission_cannot_open_page(): void
    {
        $this->seed(RoleMatrixSeeder::class);
        $graph = $this->createBuildingGraph();
        $user = $this->createUser();
        $role = Role::query()->where('name', 'building_manager')->firstOrFail();
        $role->permissions()->detach(Permission::query()->where('name', 'charge-periods.view')->pluck('id'));
        UserRoleAssignment::query()->create([
            'user_id' => $user->id, 'role_id' => $role->id,
            'scope_type' => $graph['building']->getMorphClass(), 'scope_id' => $graph['building']->id, 'is_active' => true,
        ]);
        $this->actingAs($user)->get('/management/operations/charge-periods')->assertForbidden();
    }
}
