<?php

namespace Tests\Feature\Web;

use App\Models\FinancialCategory;
use App\Models\ManagedUserScope;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UnitOccupancy;
use App\Models\UnitOwnership;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Database\Seeders\RoleMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

final class ManagerAccessRegressionWebTest extends TestCase
{
    use RefreshDatabase, CreatesBuildingDomainData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleMatrixSeeder::class);
    }

    private function assigned(string $role, $scope = null): User
    {
        $user = $this->createUser(['mobile_verified_at' => now()]);
        UserRoleAssignment::query()->create([
            'user_id' => $user->id, 'role_id' => Role::where('name', $role)->value('id'),
            'scope_type' => $scope?->getMorphClass(), 'scope_id' => $scope?->id, 'is_active' => true,
        ]);
        return $user;
    }

    public function test_scoped_manager_cannot_modify_staff_accounts_even_when_they_own_a_unit(): void
    {
        $graph = $this->createBuildingGraph();
        $manager = $this->assigned('building_manager', $graph['building']);
        foreach (['superadmin', 'complex_manager', 'building_manager', 'finance_manager', 'operator'] as $role) {
            $scope = match ($role) { 'superadmin' => null, 'complex_manager' => $graph['complex'], default => $graph['building'] };
            $target = $this->assigned($role, $scope);
            UnitOwnership::query()->create(['unit_id' => $graph['unit']->id, 'user_id' => $target->id, 'starts_at' => today()->subDay(), 'is_active' => true, 'ownership_percentage' => 10]);
            $password = $target->password;
            $this->actingAs($manager)->patchJson('/management/data/users/'.$target->id, ['password' => 'Changed!123', 'mobile' => '09990000999', 'is_blocked' => true])->assertForbidden();
            $this->assertSame($password, $target->refresh()->password);
            $this->assertFalse($target->is_blocked);
            $row = collect($this->getJson('/management/data/users?per_page=100')->assertOk()->json('data'))->firstWhere('id', $target->id);
            $this->assertNotNull($row);
            $this->assertFalse($row['can_update']);
            // A disabled staff assignment may later be reactivated by an admin.
            $target->userRoleAssignments()->update(['is_active' => false]);
            $this->patchJson('/management/data/users/'.$target->id, ['password' => 'Changed!123'])->assertForbidden();
        }
        $this->actingAs($this->assigned('superadmin'))->patchJson('/management/data/users/'.$manager->id, ['first_name' => 'اصلاح مدیر'])->assertOk();
    }

    public function test_former_or_future_relations_do_not_allow_writes_even_with_managed_user_scope(): void
    {
        $graph = $this->createBuildingGraph();
        $manager = $this->assigned('building_manager', $graph['building']);
        foreach ([UnitOwnership::class, UnitOccupancy::class] as $model) {
            foreach (['ended', 'expired', 'future', 'current'] as $state) {
                $target = $this->createUser();
                ManagedUserScope::query()->create(['user_id' => $target->id, 'scope_type' => $graph['building']->getMorphClass(), 'scope_id' => $graph['building']->id]);
                $data = ['unit_id' => $graph['unit']->id, 'user_id' => $target->id, 'starts_at' => $state === 'future' ? today()->addDay() : today()->subDays(20), 'is_active' => $state !== 'ended', 'ends_at' => in_array($state, ['ended', 'expired']) ? today()->subDay() : null];
                $data += $model === UnitOccupancy::class ? ['occupancy_type' => 'resident'] : ['ownership_percentage' => 10];
                $model::query()->create($data);
                $response = $this->actingAs($manager)->patchJson('/management/data/users/'.$target->id, ['first_name' => 'اصلاح حساب']);
                if ($state === 'current') $response->assertOk(); else $response->assertForbidden();
                $row = collect($this->getJson('/management/data/users?per_page=100')->assertOk()->json('data'))->firstWhere('id', $target->id);
                $this->assertNotNull($row, 'Historical records should remain visible');
                $this->assertSame($state === 'current', $row['can_update']);
            }
        }
        $new = $this->postJson('/management/data/users', ['first_name' => 'کاربر', 'last_name' => 'جدید', 'mobile' => '09990000777'])->assertCreated()->json('data.id');
        $this->patchJson('/management/data/users/'.$new, ['first_name' => 'اصلاح جدید'])->assertOk();
    }

    public function test_current_relation_in_same_scope_preserves_editing_after_an_old_relation_ends(): void
    {
        $graph = $this->createBuildingGraph();
        $manager = $this->assigned('building_manager', $graph['building']);
        $resident = $this->createUser();
        UnitOwnership::query()->create(['unit_id' => $graph['unit']->id, 'user_id' => $resident->id, 'starts_at' => today()->subDays(20), 'ends_at' => today()->subDay(), 'is_active' => false, 'ownership_percentage' => 100]);
        UnitOccupancy::query()->create(['unit_id' => $graph['unit']->id, 'user_id' => $resident->id, 'starts_at' => today(), 'ends_at' => today(), 'is_active' => true, 'occupancy_type' => 'resident']);
        $this->actingAs($manager)->patchJson('/management/data/users/'.$resident->id, ['first_name' => 'ساکن فعلی'])->assertOk();
    }

    public function test_support_configuration_links_and_actions_match_global_api_permissions(): void
    {
        $graph = $this->createBuildingGraph();
        $this->actingAs($this->assigned('building_manager', $graph['building']))
            ->get('/management/operations/support-tickets')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('workspace.canManageCategories', false));
        foreach (['support-categories' => 'categories', 'support-sla' => 'sla-policies'] as $page => $api) {
            $this->get('/management/operations/'.$page)->assertForbidden();
            $this->getJson('/api/v1/support-config/'.$api)->assertForbidden();
        }
        $admin = $this->assigned('superadmin');
        $this->actingAs($admin);
        foreach (['support-categories' => 'categories', 'support-sla' => 'sla-policies'] as $page => $api) {
            $this->get('/management/operations/'.$page)->assertOk()->assertViewHas('resource', fn ($resource) => isset($resource['create'], $resource['update']));
            $this->getJson('/api/v1/support-config/'.$api)->assertOk();
        }
    }

    public function test_global_support_viewer_does_not_get_write_controls_from_a_scoped_manage_permission(): void
    {
        $graph = $this->createBuildingGraph();
        $role = Role::query()->create(['name' => 'support-global-viewer', 'display_name' => 'مشاهده تنظیمات', 'is_system' => false]);
        $role->permissions()->sync(Permission::query()->whereIn('name', ['reports.dashboard.view', 'support-config.view'])->pluck('id'));
        $viewer = $this->assigned($role->name);
        $writeRole = Role::query()->create(['name' => 'support-scoped-writer', 'display_name' => 'تنظیمات ساختمان', 'is_system' => false]);
        $writeRole->permissions()->sync(Permission::query()->where('name', 'support-config.manage')->pluck('id'));
        UserRoleAssignment::query()->create(['user_id' => $viewer->id, 'role_id' => $writeRole->id, 'scope_type' => $graph['building']->getMorphClass(), 'scope_id' => $graph['building']->id, 'is_active' => true]);
        $this->actingAs($viewer);
        foreach (['support-categories' => 'categories', 'support-sla' => 'sla-policies'] as $page => $api) {
            $this->get('/management/operations/'.$page)->assertOk()->assertViewHas('resource', fn ($resource) => ! isset($resource['create']) && ! isset($resource['update']));
            $this->getJson('/api/v1/support-config/'.$api)->assertOk();
            $this->postJson('/api/v1/support-config/'.$api, ['title' => 'غیرمجاز', 'priority' => 'medium', 'first_response_minutes' => 30, 'resolution_minutes' => 60])->assertForbidden();
        }
    }

    public function test_charge_categories_include_global_and_own_active_expenses_without_foreign_rows(): void
    {
        $graph = $this->createBuildingGraph();
        $foreign = $this->createBuildingGraph();
        $manager = $this->assigned('building_manager', $graph['building']);
        $category = fn ($building, $title, $type = 'expense', $active = true) => FinancialCategory::query()->create(['building_id' => $building?->id, 'title' => $title, 'type' => $type, 'is_active' => $active]);
        $global = $category(null, 'عمومی');
        $own = $category($graph['building'], 'ساختمان');
        $outside = $category($foreign['building'], 'بیگانه');
        $category(null, 'غیرفعال', 'expense', false);
        $category(null, 'درآمد', 'income');
        $this->actingAs($manager)->getJson('/management/lookups/expense_categories?building_id='.$graph['building']->id)
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonFragment(['id' => $global->id])->assertJsonFragment(['id' => $own->id])->assertJsonMissing(['id' => $outside->id]);
        $this->getJson('/management/lookups/expense_categories')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/management/lookups/financial_categories?building_id='.$graph['building']->id)->assertOk()->assertJsonFragment(['id' => $global->id])->assertJsonMissing(['id' => $outside->id]);
        $this->getJson('/management/lookups/expense_categories?building_id='.$foreign['building']->id)->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/buildings/'.$graph['building']->id.'/charge-formulas', ['title' => 'شارژ عمومی', 'builder' => ['financial_category_id' => $global->id, 'calculation_type' => 'fixed', 'fixed_amount' => 100000]])->assertCreated();
    }
}
