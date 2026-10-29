<?php

namespace Tests\Feature\Web;

use App\Models\BuildingServiceFinancialSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

final class ServiceRequestPageWebTest extends TestCase
{
    use RefreshDatabase, CreatesBuildingDomainData;

    public function test_scoped_service_workspace_reuses_the_new_shell_without_granting_financial_actions(): void
    {
        $inside = $this->createBuildingGraph(); $outside = $this->createBuildingGraph(); $user = $this->createUser();
        $this->grant($user, $inside['building'], ['service-requests.view', 'service-requests.create', 'service-requests.update']);
        $this->actingAs($user)->get('/management/operations/service-requests?building_id='.$inside['building']->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('management/services/Requests')->has('workspace.buildings', 1)->has('workspace.units', 1)
            ->where('workspace.buildings.0.id', $inside['building']->id)
            ->where('workspace.buildings.0.canCreate', true)
            ->where('workspace.buildings.0.abilities', fn ($abilities) => $abilities['service-requests.update'] && ! $abilities['service-finance.settle'])
            ->where('workspace.units.0.building_id', $inside['building']->id)
            ->where('workspace.units.0.canUseWallet', false));
        $this->assertSame(0, BuildingServiceFinancialSetting::query()->count(), 'Rendering a UI must not create financial settings.');
    }

    public function test_assigned_request_context_is_visible_without_exposing_unrelated_buildings(): void
    {
        $inside = $this->createBuildingGraph(); $assigned = $this->createBuildingGraph(); $outside = $this->createBuildingGraph();
        $user = $this->createUser(); $requester = $this->createUser();
        $this->grant($user, $inside['building'], ['service-requests.view']);
        $request = ServiceRequest::query()->create(['request_number' => 'SR-UI-1', 'building_id' => $assigned['building']->id, 'requested_by' => $requester->id, 'assigned_to' => $user->id, 'title' => 'تعمیر', 'type' => 'برق', 'priority' => 'normal', 'status' => 'assigned']);
        $this->actingAs($user)->get('/management/operations/service-requests')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('management/services/Requests')->has('workspace.buildings', 2)
            ->where('workspace.buildings', fn ($items) => collect($items)->pluck('id')->sort()->values()->all() === collect([$inside['building']->id, $assigned['building']->id])->sort()->values()->all()));
        $this->getJson('/api/v1/service-requests/'.$request->id)->assertOk()
            ->assertJsonPath('data.assigned_to.id', $user->id)->assertJsonPath('data.requested_by.id', $requester->id)
            ->assertJsonPath('data.wallet_payment', null)->assertJsonCount(0, 'data.quotes');
    }

    private function grant($user, $building, array $names): void
    {
        $role = Role::query()->create(['name' => 'service-ui-'.uniqid(), 'display_name' => 'خدمات', 'is_system' => false]);
        $role->permissions()->sync(collect(['reports.dashboard.view', ...$names])->map(fn ($name) => Permission::query()->firstOrCreate(['name' => $name], ['display_name' => $name, 'module' => explode('.', $name)[0]])->id));
        UserRoleAssignment::query()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => $building->getMorphClass(), 'scope_id' => $building->id, 'is_active' => true]);
    }
}
