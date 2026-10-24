<?php

namespace Tests\Feature\Web;

use App\Models\Role;
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
            ->assertViewHas('resource', fn (array $resource): bool => collect($resource['fields'])->firstWhere('name', 'builder')['type'] === 'charge_formula_builder');

        $this->get('/management/operations/charge-periods')
            ->assertOk()->assertDontSee('buildino-charge-formula-ui.js')
            ->assertDontSee('charge-guide-title');
    }
}
