<?php

namespace Tests\Feature\Web;

use App\Models\Building;
use App\Models\BuildingStaff;
use App\Models\UnitOwnership;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;
use Database\Seeders\AccessScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildingStaffTest extends TestCase
{
    use RefreshDatabase;

    private function setupStaff(): array
    {
        $this->seed(AccessScenarioSeeder::class);
        $manager = User::where('email', 'role.building@buildino.local')->firstOrFail();
        $owner = User::where('email', 'role.owner@buildino.local')->firstOrFail();
        $building = UnitOwnership::where('user_id', $owner->id)->firstOrFail()->unit->floor->block->building;
        $this->actingAs($manager);

        return [$manager, $owner, $building];
    }

    private function data(): array
    {
        return ['first_name' => 'کارمند', 'last_name' => 'آزمایشی', 'mobile' => '09129990001', 'employee_type' => 'concierge', 'services' => ['reservations'], 'permissions' => ['facilities.view', 'facility-reservations.view', 'facility-reservations.approve'], 'is_active' => 1];
    }

    public function test_scoped_employee_creation_edit_and_revocation(): void
    {
        [$manager,$owner,$building] = $this->setupStaff();
        $this->get('/management/operations')->assertOk()->assertSee('ثبت کارمندان ساختمان');
        $this->get('/management/operations/building-staff')->assertOk();
        $this->post(route('management.building-staff.store', $building), $this->data())->assertSessionHasNoErrors()->assertRedirect();
        $employee = BuildingStaff::firstOrFail();
        $user = $employee->user;
        $checker = app(PermissionChecker::class);
        $this->assertTrue($checker->allows($user, 'facility-reservations.approve', $building));
        $this->assertFalse($checker->allows($user, 'facility-reservations.approve', Building::where('id', '!=', $building->id)->firstOrFail()));
        $this->assertFalse($checker->allows($user, 'building-staff.manage', $building));
        $this->assertNull($user->mobile_verified_at);
        $user->update(['mobile_verified_at' => now()]);
        $this->actingAs($user)->get('/management')->assertRedirect(route('management.operations.index'));
        $this->get('/management/operations')->assertOk()->assertSee('رزرو');
        $this->get('/management/operations/building-staff')->assertForbidden();
        $this->actingAs($manager);
        $this->get('/management/operations/building-staff?building_id='.$building->id.'&edit='.$employee->id)->assertOk()->assertSee('آزمایشی');
        $data = $this->data();
        $data['is_active'] = 0;
        $this->patch(route('management.building-staff.update', [$building, $employee]), $data)->assertSessionHasNoErrors();
        $this->assertFalse($checker->allows($user, 'facility-reservations.approve', $building));
    }

    public function test_existing_account_preserved_and_escalation_rejected(): void
    {
        [$manager,$owner,$building] = $this->setupStaff();
        $data = $this->data();
        $data['mobile'] = $owner->mobile;
        $name = $owner->first_name;
        $password = $owner->password;
        $this->post(route('management.building-staff.store', $building), $data)->assertSessionHasNoErrors();
        $this->assertSame($name, $owner->fresh()->first_name);
        $this->assertSame($password, $owner->fresh()->password);
        $this->post(route('management.building-staff.store', $building), $data)->assertSessionHasErrors('mobile');
        $data = $this->data();
        $data['permissions'] = ['users.create'];
        $this->post(route('management.building-staff.store', $building), $data)->assertSessionHasErrors('permissions.0');
        $data = $this->data();
        $data['services'] = ['security'];
        $this->post(route('management.building-staff.store', $building), $data)->assertSessionHasErrors('services.0');
        $this->post(route('management.building-staff.store', Building::where('id', '!=', $building->id)->firstOrFail()), $this->data())->assertForbidden();
        $this->actingAs($owner)->post(route('management.building-staff.store', $building), $this->data())->assertForbidden();
        $this->assertSame(1,BuildingStaff::count());
    }
}
