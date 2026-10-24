<?php

namespace Tests\Feature\Web;

use App\Models\Permission;
use App\Models\Role;
use App\Models\UserRoleAssignment;
use Database\Seeders\RoleMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

final class FacilityReservationPageWebTest extends TestCase
{
    use CreatesBuildingDomainData;
    use RefreshDatabase;

    public function test_reservation_page_requires_login(): void
    {
        $this->get('/management/operations/reservations')->assertRedirect(route('login'));
    }

    public function test_reservation_context_only_contains_authorized_building_and_units(): void
    {
        $this->seed(RoleMatrixSeeder::class);
        $own = $this->createBuildingGraph();
        $other = $this->createBuildingGraph();
        $own['building']->update(['title' => 'ساختمان مجاز رزرو']);
        $other['building']->update(['title' => 'ساختمان نامجاز رزرو']);
        $user = $this->createUser();
        $role = Role::query()->where('name', 'building_manager')->firstOrFail();
        UserRoleAssignment::query()->create([
            'user_id' => $user->id, 'role_id' => $role->id,
            'scope_type' => $own['building']->getMorphClass(), 'scope_id' => $own['building']->id, 'is_active' => true,
        ]);

        $this->actingAs($user)->get('/management/operations/reservations')
            ->assertOk()->assertViewIs('management.facility-reservations')
            ->assertSee('data-reservation-page', false)
            ->assertSee('ساختمان مجاز رزرو')->assertDontSee('ساختمان نامجاز رزرو')
            ->assertViewHas('reservationConfig', fn (array $config): bool => $config['buildings']->count() === 1
                && $config['buildings']->first()['canCreate'] && $config['buildings']->first()['canApprove']
                && $config['units']->pluck('id')->all() === [$own['unit']->id]
                && $config['actorId'] === $user->id);

        $role->permissions()->detach(Permission::query()->whereIn('name', [
            'facility-reservations.create', 'facility-reservations.approve', 'facility-reservations.cancel',
        ])->pluck('id'));
        $this->actingAs($user->fresh())->get('/management/operations/reservations')
            ->assertOk()->assertViewHas('reservationConfig', fn (array $config): bool => $config['units']->isEmpty()
                && ! $config['buildings']->first()['canCreate'] && ! $config['buildings']->first()['canApprove']
                && ! $config['buildings']->first()['canCancel']);
    }
}
