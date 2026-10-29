<?php

namespace Tests\Feature\Web;

use App\Models\BuildingExpense;
use App\Models\BuildingIncome;
use App\Models\BuildingBankAccount;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

final class FinanceWorkspaceWebTest extends TestCase
{
    use RefreshDatabase, CreatesBuildingDomainData;

    public function test_financial_pages_share_navigation_and_only_expose_authorized_buildings(): void
    {
        $inside = $this->createBuildingGraph(); $outside = $this->createBuildingGraph(); $user = $this->createUser();
        $resources = collect(config('management_crud.resources'))->filter(fn ($resource) => $resource['group'] === 'finance');
        $this->grant($user, [...$resources->pluck('permission')->unique()->all(), 'reports.dashboard.view'], $inside['building']);
        $this->actingAs($user);
        foreach ($resources as $key => $resource) {
            $response = $this->get('/management/operations/'.$key)->assertOk();
            if (in_array($key, ['charge-formulas', 'charge-periods'], true)) {
                $response->assertSee('data-finance-navigation', false)->assertDontSee('crud-sibling-nav', false);
                continue;
            }
            $response->assertInertia(fn (Assert $page) => $page
                ->component(in_array($key, ['invoices', 'invoice-installments', 'payments']) ? 'management/finance/Invoices' : 'management/finance/Records')
                ->where('resourceKey', $key)->has('workspace.buildings', 1)
                ->where('workspace.buildings.0.id', $inside['building']->id)
                ->where('workspace.buildings.0.abilities', fn ($abilities) => $abilities['invoices.create'] === false)
                ->where('workspace.buildings.0.abilities', fn ($abilities) => $abilities['payments.verify'] === false)
                ->has('workspace.navigation', 13));
        }
        $this->get('/management/operations/users')->assertForbidden();
    }

    public function test_actions_are_scoped_and_withdrawal_selector_only_offers_verified_active_accounts(): void
    {
        $graph = $this->createBuildingGraph(); $other = $this->createBuildingGraph(); $user = $this->createUser();
        $this->grant($user, ['reports.dashboard.view', 'wallet-payouts.view', 'wallet-payouts.create', 'wallet-payouts.approve'], $graph['building']);
        foreach ([[true, true, $graph], [false, true, $graph], [true, false, $graph], [true, true, $other]] as $index => [$verified, $active, $scope]) {
            BuildingBankAccount::query()->create(['building_id' => $scope['building']->id, 'account_holder_name' => 'حساب '.$index, 'iban' => 'IR'.str_repeat((string) $index, 24), 'is_verified' => $verified, 'is_active' => $active]);
        }
        $this->actingAs($user)->get('/management/operations/wallet-payouts')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('workspace.buildings.0.abilities', fn ($abilities) => $abilities['wallet-payouts.approve'] === true)
            ->where('workspace.buildings.0.abilities', fn ($abilities) => $abilities['wallet-payouts.pay'] === false)
            ->has('workspace.lookups.building_bank_account_id', 1)
            ->where('workspace.lookups.building_bank_account_id.0.building_id', $graph['building']->id));
    }

    public function test_cost_and_income_building_filters_preserve_authorization_and_unfiltered_results(): void
    {
        $inside = $this->createBuildingGraph(); $outside = $this->createBuildingGraph(); $global = $this->createUser();
        $this->grant($global, ['expenses.view', 'incomes.view'], null);
        foreach ([$inside, $outside] as $graph) {
            BuildingExpense::query()->create(['building_id' => $graph['building']->id, 'title' => 'هزینه', 'amount' => 100, 'expense_date' => today(), 'status' => 'draft', 'created_by' => $global->id]);
            BuildingIncome::query()->create(['building_id' => $graph['building']->id, 'title' => 'درآمد', 'amount' => 100, 'income_date' => today(), 'status' => 'draft', 'created_by' => $global->id]);
        }
        foreach (['expenses', 'incomes'] as $resource) {
            $this->actingAs($global)->getJson('/api/v1/'.$resource)->assertOk()->assertJsonCount(2, 'data');
            $this->getJson('/api/v1/'.$resource.'?building_id='.$inside['building']->id)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.building_id', $inside['building']->id);
            $this->getJson('/api/v1/'.$resource.'?building_id=wrong')->assertUnprocessable();
        }
        $scoped = $this->createUser(); $this->grant($scoped, ['expenses.view', 'incomes.view'], $inside['building']);
        foreach (['expenses', 'incomes'] as $resource) $this->actingAs($scoped)->getJson('/api/v1/'.$resource.'?building_id='.$outside['building']->id)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_block_scoped_expense_manager_keeps_block_actions_without_building_wide_access(): void
    {
        $graph = $this->createBuildingGraph(); $user = $this->createUser();
        $this->grant($user, ['expenses.view', 'expenses.create', 'expenses.update'], $graph['block']);
        $this->grant($user, ['reports.dashboard.view'], $graph['building']);
        $this->actingAs($user)->get('/management/operations/expenses')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('workspace.buildings', 1)->where('workspace.buildings.0.abilities', fn ($abilities) => $abilities['expenses.create'] === false)
            ->where('workspace.blocks.0.value', $graph['block']->id)->where('workspace.blocks.0.abilities.create', true));
    }

    private function grant($user, array $names, $scope): void
    {
        $role = Role::query()->create(['name' => 'finance-'.uniqid(), 'display_name' => 'نقش مالی', 'is_system' => false]);
        $role->permissions()->sync(collect($names)->map(fn ($name) => Permission::query()->firstOrCreate(['name' => $name], ['display_name' => $name, 'module' => explode('.', $name)[0]])->id)->all());
        UserRoleAssignment::query()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => $scope?->getMorphClass(), 'scope_id' => $scope?->id, 'is_active' => true]);
    }
}
