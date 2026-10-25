<?php

namespace Tests\Feature\Web;

use App\Models\Role;
use App\Models\Permission;
use App\Models\UserRoleAssignment;
use Database\Seeders\RoleMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

final class ChargeFormulaPageWebTest extends TestCase
{
    use CreatesBuildingDomainData;
    use RefreshDatabase;

    public function test_formula_page_keeps_existing_builder_and_loads_process_guidance_only_on_formula_page(): void
    {
        $this->seed(RoleMatrixSeeder::class);
        $graph = $this->createBuildingGraph();
        $user = $this->createUser();
        UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => Role::query()->where('name', 'building_manager')->value('id'),
            'scope_type' => $graph['building']->getMorphClass(),
            'scope_id' => $graph['building']->id, 'is_active' => true,
        ]);
        $this->actingAs($user)->get('/management/operations/charge-formulas')
            ->assertOk()->assertViewIs('management.operations.resource')
            ->assertSee('تعریف فرمول شارژ · P12')
            ->assertSee('اتصال جداگانه به دوره وجود ندارد')
            ->assertSee('buildino-charge-formula-ui.js')
            ->assertSee('data-inline-editor', false)
            ->assertSee('data-charge-location', false)
            ->assertSee('data-charge-directory', false)
            ->assertViewHas('formulaBuildings', fn ($buildings): bool => $buildings->count() === 1
                && $buildings->first()['canCreate'] && $buildings->first()['canUpdate'])
            ->assertViewHas('resource', fn (array $resource): bool => collect($resource['fields'])->firstWhere('name', 'builder')['type'] === 'charge_formula_builder');

        $this->get('/management/operations/charge-periods')
            ->assertOk()->assertDontSee('buildino-charge-formula-ui.js')
            ->assertDontSee('charge-guide-title');
    }

    public function test_formula_page_does_not_offer_foreign_buildings_or_mutations_to_viewers(): void
    {
        $this->seed(RoleMatrixSeeder::class);
        $own = $this->createBuildingGraph();
        $other = $this->createBuildingGraph();
        $own['building']->update(['title' => 'ساختمان فرمول مجاز']);
        $other['building']->update(['title' => 'ساختمان فرمول غیرمجاز']);
        $user = $this->createUser();
        $role = Role::query()->where('name', 'building_manager')->firstOrFail();
        $role->permissions()->detach(Permission::query()->whereIn('name', ['charge-formulas.create', 'charge-formulas.update'])->pluck('id'));
        UserRoleAssignment::query()->create([
            'user_id' => $user->id, 'role_id' => $role->id,
            'scope_type' => $own['building']->getMorphClass(), 'scope_id' => $own['building']->id, 'is_active' => true,
        ]);
        $this->actingAs($user)->get('/management/operations/charge-formulas')->assertOk()
            ->assertSee('ساختمان فرمول مجاز')->assertDontSee('ساختمان فرمول غیرمجاز')
            ->assertViewHas('formulaBuildings', fn ($buildings): bool => $buildings->count() === 1
                && ! $buildings->first()['canCreate'] && ! $buildings->first()['canUpdate']);
    }
}
