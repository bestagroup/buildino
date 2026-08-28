<?php

namespace Tests\Feature\Financial;

use App\Enums\ExpensePayerResponsibility;
use App\Enums\OccupancyType;
use App\Enums\UnitUsageType;
use App\Models\Block;
use App\Models\Building;
use App\Models\ChargeExpenseAllocation;
use App\Models\Complex;
use App\Models\Floor;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitInvoice;
use App\Models\UnitOccupancy;
use App\Models\UnitOwnership;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\Web\ManagementDashboardAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SharedExpenseResponsibilityFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_posted_shared_expenses_are_allocated_and_invoiced_to_residents_and_owners(): void
    {
        $manager = $this->user('09125550001', 'shared-manager@example.test');
        $residentA = $this->user('09125550002', 'resident-a@example.test');
        $residentA2 = $this->user('09125550003', 'resident-a2@example.test');
        $residentB = $this->user('09125550004', 'resident-b@example.test');
        $ownerA = $this->user('09125550005', 'owner-a@example.test');
        $ownerB = $this->user('09125550006', 'owner-b@example.test');

        $graph = $this->buildingWithTwoUnits();
        [$unitA, $unitB] = $graph['units'];

        $this->occupy($unitA, $residentA, true);
        $this->occupy($unitA, $residentA2, false);
        $this->occupy($unitB, $residentB, true);
        $this->own($unitA, $ownerA);
        $this->own($unitB, $ownerB);

        $role = $this->role('shared-expense-manager', [
            'expenses.view',
            'expenses.create',
            'expenses.update',
            'charge-periods.create',
            'charge-periods.calculate',
            'charge-periods.issue',
        ]);
        $this->assign($manager, $role, $graph['building']);

        Sanctum::actingAs($manager);

        $waterId = $this->postJson('/api/v1/expenses', [
            'building_id' => $graph['building']->id,
            'title' => 'آب مشترک',
            'amount' => 300_000,
            'expense_date' => '2026-08-10',
            'allocation_method' => 'persons',
            'payer_responsibility' => 'resident',
            'status' => 'posted',
        ])
            ->assertCreated()
            ->assertJsonPath('data.allocation_method', 'persons')
            ->assertJsonPath('data.payer_responsibility', 'resident')
            ->json('data.id');

        $repairId = $this->postJson('/api/v1/expenses', [
            'building_id' => $graph['building']->id,
            'title' => 'تعمیرات اساسی نما',
            'amount' => 300_000,
            'expense_date' => '2026-08-12',
            'allocation_method' => 'equal',
            'payer_responsibility' => 'owner',
            'status' => 'posted',
        ])
            ->assertCreated()
            ->json('data.id');

        $periodId = $this->postJson(
            "/api/v1/buildings/{$graph['building']->id}/charge-periods",
            [
                'title' => 'شارژ مرداد ۱۴۰۵',
                'period_start' => '2026-08-01',
                'period_end' => '2026-08-31',
                'due_date' => '2026-09-10',
            ]
        )
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/v1/charge-periods/{$periodId}/calculate")
            ->assertOk()
            ->assertJsonPath('data.expense_allocations_count', 4);

        $this->assertAllocation(
            $periodId,
            $waterId,
            $unitA,
            200_000,
            ExpensePayerResponsibility::Resident,
            $residentA
        );
        $this->assertAllocation(
            $periodId,
            $waterId,
            $unitB,
            100_000,
            ExpensePayerResponsibility::Resident,
            $residentB
        );
        $this->assertAllocation(
            $periodId,
            $repairId,
            $unitA,
            150_000,
            ExpensePayerResponsibility::Owner,
            $ownerA
        );
        $this->assertAllocation(
            $periodId,
            $repairId,
            $unitB,
            150_000,
            ExpensePayerResponsibility::Owner,
            $ownerB
        );

        $this->postJson("/api/v1/charge-periods/{$periodId}/issue")
            ->assertOk()
            ->assertJsonPath('data.invoices_count', 4);

        $residentInvoice = UnitInvoice::query()
            ->where('charge_period_id', $periodId)
            ->where('payer_user_id', $residentA->id)
            ->firstOrFail();
        $ownerInvoice = UnitInvoice::query()
            ->where('charge_period_id', $periodId)
            ->where('payer_user_id', $ownerA->id)
            ->firstOrFail();

        $this->assertSame(200_000, (int) $residentInvoice->total_amount);
        $this->assertSame(150_000, (int) $ownerInvoice->total_amount);

        Sanctum::actingAs($residentA);
        $this->getJson("/api/v1/invoices/{$residentInvoice->id}")
            ->assertOk()
            ->assertJsonPath('data.payer_user_id', $residentA->id);
        $this->getJson("/api/v1/invoices/{$ownerInvoice->id}")
            ->assertForbidden();

        Sanctum::actingAs($ownerA);
        $this->getJson("/api/v1/invoices/{$ownerInvoice->id}")
            ->assertOk()
            ->assertJsonPath('data.payer_user_id', $ownerA->id);
        $this->getJson("/api/v1/invoices/{$residentInvoice->id}")
            ->assertForbidden();
    }

    public function test_block_manager_can_manage_expenses_only_for_assigned_block(): void
    {
        $manager = $this->user('09125551001', 'block-expense-manager@example.test');
        $graph = $this->buildingWithTwoBlocks();
        $role = $this->role('block-expense-manager', [
            'reports.dashboard.view',
            'expenses.view',
            'expenses.create',
            'expenses.update',
        ]);

        $this->assign($manager, $role, $graph['blocks'][0]);
        Sanctum::actingAs($manager);

        $createdId = $this->postJson('/api/v1/expenses', [
            'building_id' => $graph['building']->id,
            'block_id' => $graph['blocks'][0]->id,
            'title' => 'برق عمومی بلوک الف',
            'amount' => 120_000,
            'expense_date' => '2026-08-15',
            'allocation_method' => 'equal',
            'payer_responsibility' => 'resident',
            'status' => 'posted',
        ])
            ->assertCreated()
            ->assertJsonPath('data.block_id', $graph['blocks'][0]->id)
            ->json('data.id');

        $this->postJson('/api/v1/expenses', [
            'building_id' => $graph['building']->id,
            'block_id' => $graph['blocks'][1]->id,
            'title' => 'هزینه خارج از محدوده',
            'amount' => 50_000,
            'expense_date' => '2026-08-15',
        ])->assertForbidden();

        $this->postJson('/api/v1/expenses', [
            'building_id' => $graph['building']->id,
            'title' => 'هزینه کل ساختمان',
            'amount' => 50_000,
            'expense_date' => '2026-08-15',
        ])->assertForbidden();

        $this->patchJson("/api/v1/expenses/{$createdId}", [
            'block_id' => $graph['blocks'][1]->id,
        ])->assertForbidden();

        $this->patchJson("/api/v1/expenses/{$createdId}", [
            'block_id' => null,
        ])->assertForbidden();

        $this->getJson('/api/v1/expenses')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $createdId);

        $accessible = app(ManagementDashboardAccessService::class)
            ->accessibleBuildings($manager);

        $this->assertSame(
            [$graph['building']->id],
            $accessible->modelKeys()
        );
    }

    private function assertAllocation(
        int $periodId,
        int $expenseId,
        Unit $unit,
        int $amount,
        ExpensePayerResponsibility $responsibility,
        User $payer
    ): void {
        $allocation = ChargeExpenseAllocation::query()
            ->where('charge_period_id', $periodId)
            ->where('building_expense_id', $expenseId)
            ->where('unit_id', $unit->id)
            ->firstOrFail();

        $this->assertSame($amount, (int) $allocation->allocated_amount);
        $this->assertSame($responsibility, $allocation->payer_responsibility);
        $this->assertSame($payer->id, (int) $allocation->payer_user_id);
    }

    private function buildingWithTwoUnits(): array
    {
        $graph = $this->buildingBase('SHARED');
        $floor = $this->floor($graph['block'], 1);

        $graph['units'] = [
            $this->unit($floor, '101', 100),
            $this->unit($floor, '102', 50),
        ];

        return $graph;
    }

    private function buildingWithTwoBlocks(): array
    {
        $graph = $this->buildingBase('BLOCK');
        $secondBlock = Block::query()->create([
            'building_id' => $graph['building']->id,
            'title' => 'بلوک ب',
            'sort_order' => 2,
            'is_active' => true,
        ]);
        $graph['blocks'] = [$graph['block'], $secondBlock];

        $this->unit($this->floor($graph['block'], 1), '101', 100);
        $this->unit($this->floor($secondBlock, 1), '201', 90);

        return $graph;
    }

    private function buildingBase(string $suffix): array
    {
        $complex = Complex::query()->create([
            'code' => "CMP-{$suffix}",
            'title' => "Complex {$suffix}",
            'province' => 'Tehran',
            'city' => 'Tehran',
            'is_active' => true,
        ]);
        $building = Building::query()->create([
            'complex_id' => $complex->id,
            'code' => "BLD-{$suffix}",
            'title' => "Building {$suffix}",
            'currency' => 'IRR',
            'is_active' => true,
        ]);
        $block = Block::query()->create([
            'building_id' => $building->id,
            'title' => 'بلوک الف',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return compact('complex', 'building', 'block');
    }

    private function floor(Block $block, int $number): Floor
    {
        return Floor::query()->create([
            'block_id' => $block->id,
            'floor_number' => $number,
            'title' => "طبقه {$number}",
            'sort_order' => $number,
        ]);
    }

    private function unit(Floor $floor, string $number, int $area): Unit
    {
        return Unit::query()->create([
            'floor_id' => $floor->id,
            'unit_number' => $number,
            'title' => "واحد {$number}",
            'area' => $area,
            'bedrooms' => 2,
            'usage_type' => UnitUsageType::Residential->value,
            'is_active' => true,
        ]);
    }

    private function occupy(Unit $unit, User $user, bool $primary): void
    {
        UnitOccupancy::query()->create([
            'unit_id' => $unit->id,
            'user_id' => $user->id,
            'occupancy_type' => OccupancyType::Resident->value,
            'starts_at' => '2026-01-01',
            'is_primary' => $primary,
            'is_active' => true,
        ]);
    }

    private function own(Unit $unit, User $user): void
    {
        UnitOwnership::query()->create([
            'unit_id' => $unit->id,
            'user_id' => $user->id,
            'ownership_percentage' => 100,
            'starts_at' => '2026-01-01',
            'is_primary' => true,
            'is_active' => true,
        ]);
    }

    private function user(string $mobile, string $email): User
    {
        return User::query()->create([
            'first_name' => 'کاربر',
            'last_name' => 'آزمایشی',
            'mobile' => $mobile,
            'email' => $email,
            'mobile_verified_at' => now(),
            'email_verified_at' => now(),
            'password' => 'TestPassword123!',
            'is_active' => true,
            'is_blocked' => false,
        ]);
    }

    private function role(string $name, array $permissions): Role
    {
        $role = Role::query()->create([
            'name' => $name,
            'display_name' => $name,
            'is_system' => true,
        ]);

        foreach ($permissions as $name) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $name],
                [
                    'display_name' => $name,
                    'module' => explode('.', $name)[0],
                ]
            );
            $role->permissions()->syncWithoutDetaching($permission->id);
        }

        return $role;
    }

    private function assign(User $user, Role $role, mixed $scope): void
    {
        UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => $scope->getMorphClass(),
            'scope_id' => $scope->getKey(),
            'starts_at' => now()->subDay(),
            'is_active' => true,
        ]);
    }
}
