<?php

namespace Tests\Feature\Security;

use App\Enums\ChargeCalculationType;
use App\Enums\ChargePeriodStatus;
use App\Enums\FinancialAccountType;
use App\Enums\FinancialTransactionType;
use App\Enums\InvoiceStatus;
use App\Enums\LedgerEntryType;
use App\Enums\OccupancyType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UnitUsageType;
use App\Models\Block;
use App\Models\BuildingExpense;
use App\Models\Building;
use App\Models\Complex;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\Floor;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitInvoice;
use App\Models\UnitOccupancy;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialCoreFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_charge_period_uses_persistent_formulas_for_expense_allocation_and_fixed_debt(): void
    {
        $manager = $this->createUser(
            '09123330001',
            'finance-manager@example.test'
        );

        $structure = $this->createStructure(
            'FIN',
            100
        );

        $secondUnit = Unit::query()->create([
            'floor_id' => $structure['floor']->id,
            'unit_number' => '102-FIN',
            'title' => 'Unit 102 FIN',
            'area' => 50,
            'bedrooms' => 2,
            'usage_type' =>
                UnitUsageType::cases()[0]->value,
            'is_active' => true,
        ]);

        $gas = FinancialCategory::query()->create([
            'building_id' =>
                $structure['building']->id,
            'title' => 'Gas',
            'type' => 'expense',
            'is_active' => true,
        ]);

        $fixedCategory =
            FinancialCategory::query()->create([
                'building_id' =>
                    $structure['building']->id,
                'title' => 'Fixed monthly charge',
                'type' => 'expense',
                'is_active' => true,
            ]);

        BuildingExpense::query()->create([
            'building_id' =>
                $structure['building']->id,
            'financial_category_id' =>
                $gas->id,
            'title' => 'August gas bill',
            'amount' => 300000,
            'expense_date' => '2026-08-15',
            'status' => 'posted',
            'created_by' => $manager->id,
            'approved_by' => $manager->id,
            'approved_at' => now(),
            'posted_at' => now(),
        ]);

        $role = $this->createRoleWithPermissions(
            'finance-manager',
            [
                'charge-formulas.create',
                'charge-periods.create',
                'charge-periods.calculate',
                'charge-periods.issue',
            ]
        );

        $this->assignRole(
            $manager,
            $role,
            $structure['building']
        );

        Sanctum::actingAs($manager);

        $areaFormulaId = $this->postJson(
            "/api/v1/buildings/{$structure['building']->id}/charge-formulas",
            [
                'title' => 'Gas by area',
                'builder' => [
                    'calculation_type' =>
                        ChargeCalculationType::Area->value,
                    'financial_category_id' =>
                        $gas->id,
                ],
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.calculation_type',
                ChargeCalculationType::Area->value
            )
            ->assertJsonPath(
                'data.configuration.builder_version',
                2
            )
            ->assertJsonPath(
                'data.items.0.financial_category_id',
                $gas->id
            )
            ->assertJsonPath(
                'data.items.0.base_amount',
                0
            )
            ->json('data.id');

        $fixedFormulaId = $this->postJson(
            "/api/v1/buildings/{$structure['building']->id}/charge-formulas",
            [
                'title' => 'Fixed monthly debt',
                'builder' => [
                    'calculation_type' =>
                        ChargeCalculationType::Fixed->value,
                    'financial_category_id' =>
                        $fixedCategory->id,
                    'fixed_amount' => 50000,
                ],
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.items.0.base_amount',
                50000
            )
            ->json('data.id');

        $periodId = $this->postJson(
            "/api/v1/buildings/{$structure['building']->id}/charge-periods",
            [
                'title' => 'August 2026',
                'period_start' => '2026-08-01',
                'period_end' => '2026-08-31',
                'due_date' => '2026-09-10',
            ]
        )
            ->assertCreated()
            ->json('data.id');

        $this->postJson(
            "/api/v1/charge-periods/{$periodId}/calculate"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                ChargePeriodStatus::Calculated->value
            );

        /*
         * Gas bill: 300,000 distributed by area 100:50.
         * Unit A = 200,000, Unit B = 100,000.
         */
        $this->assertDatabaseHas(
            'charge_expense_allocations',
            [
                'charge_period_id' => $periodId,
                'building_expense_id' =>
                    BuildingExpense::query()
                        ->where('title', 'August gas bill')
                        ->value('id'),
                'unit_id' => $structure['unit']->id,
                'allocated_amount' => 200000,
            ]
        );

        $this->assertDatabaseHas(
            'charge_expense_allocations',
            [
                'charge_period_id' => $periodId,
                'unit_id' => $secondUnit->id,
                'allocated_amount' => 100000,
            ]
        );

        /*
         * Variable formulas no longer create an independent charge.
         * Only the fixed formula creates a direct calculation.
         */
        $this->assertDatabaseMissing(
            'charge_calculations',
            [
                'charge_period_id' => $periodId,
                'charge_formula_id' => $areaFormulaId,
            ]
        );

        $this->assertDatabaseHas(
            'charge_calculations',
            [
                'charge_period_id' => $periodId,
                'unit_id' => $structure['unit']->id,
                'charge_formula_id' => $fixedFormulaId,
                'calculated_amount' => 50000,
            ]
        );

        $this->postJson(
            "/api/v1/charge-periods/{$periodId}/issue"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                ChargePeriodStatus::Issued->value
            );

        $invoiceA = UnitInvoice::query()
            ->where('charge_period_id', $periodId)
            ->where(
                'unit_id',
                $structure['unit']->id
            )
            ->firstOrFail();

        $invoiceB = UnitInvoice::query()
            ->where('charge_period_id', $periodId)
            ->where('unit_id', $secondUnit->id)
            ->firstOrFail();

        $this->assertSame(
            250000,
            (int) $invoiceA->total_amount
        );

        $this->assertSame(
            150000,
            (int) $invoiceB->total_amount
        );
    }

    public function test_resident_can_view_and_create_payment_only_for_own_invoice(): void
    {
        $resident = $this->createUser(
            '09123331001',
            'resident-finance@example.test'
        );

        $own = $this->createStructure('OWN');
        $other = $this->createStructure('OTHER');

        UnitOccupancy::query()->create([
            'unit_id' => $own['unit']->id,
            'user_id' => $resident->id,
            'occupancy_type' =>
                OccupancyType::Resident->value,
            'starts_at' => now()->toDateString(),
            'is_primary' => true,
            'is_active' => true,
        ]);

        $ownInvoice = $this->createIssuedInvoice(
            $own['building'],
            $own['unit'],
            200000
        );

        $otherInvoice = $this->createIssuedInvoice(
            $other['building'],
            $other['unit'],
            150000
        );

        Sanctum::actingAs($resident);

        $this->getJson(
            "/api/v1/invoices/{$ownInvoice->id}"
        )->assertOk();

        $this->getJson(
            "/api/v1/invoices/{$otherInvoice->id}"
        )->assertForbidden();

        $payment = $this->postJson(
            "/api/v1/invoices/{$ownInvoice->id}/payments",
            [
                'amount' => 50000,
                'method' => PaymentMethod::Manual->value,

                /*
                 * These fields are intentionally not accepted
                 * by StorePaymentRequest.
                 */
                'building_id' => $other['building']->id,
                'payer_user_id' => 999999,
                'status' => PaymentStatus::Paid->value,
            ]
        );

        $payment
            ->assertCreated()
            ->assertJsonPath(
                'data.building_id',
                $own['building']->id
            )
            ->assertJsonPath(
                'data.payer_user_id',
                $resident->id
            )
            ->assertJsonPath(
                'data.status',
                PaymentStatus::Pending->value
            );

        $this->postJson(
            "/api/v1/invoices/{$otherInvoice->id}/payments",
            [
                'amount' => 10000,
                'method' => PaymentMethod::Manual->value,
            ]
        )->assertForbidden();
    }

    public function test_verifying_same_payment_twice_does_not_double_allocate(): void
    {
        $resident = $this->createUser(
            '09123332001',
            'payer@example.test'
        );

        $manager = $this->createUser(
            '09123332002',
            'verifier@example.test'
        );

        $structure = $this->createStructure('PAY');

        UnitOccupancy::query()->create([
            'unit_id' => $structure['unit']->id,
            'user_id' => $resident->id,
            'occupancy_type' =>
                OccupancyType::Resident->value,
            'starts_at' => now()->toDateString(),
            'is_primary' => true,
            'is_active' => true,
        ]);

        $role = $this->createRoleWithPermissions(
            'payment-verifier',
            ['payments.verify']
        );

        $this->assignRole(
            $manager,
            $role,
            $structure['building']
        );

        $invoice = $this->createIssuedInvoice(
            $structure['building'],
            $structure['unit'],
            100000
        );

        Sanctum::actingAs($resident);

        $paymentId = $this->postJson(
            "/api/v1/invoices/{$invoice->id}/payments",
            [
                'amount' => 40000,
                'method' => PaymentMethod::Manual->value,
            ]
        )
            ->assertCreated()
            ->json('data.id');

        Sanctum::actingAs($manager);

        $this->postJson(
            "/api/v1/payments/{$paymentId}/verify"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                PaymentStatus::Paid->value
            );

        $this->postJson(
            "/api/v1/payments/{$paymentId}/verify"
        )->assertOk();

        $invoice->refresh();

        $this->assertSame(
            40000,
            (int) $invoice->paid_amount
        );

        $this->assertSame(
            60000,
            (int) $invoice->outstanding_amount
        );

        $this->assertSame(
            InvoiceStatus::Partial,
            $invoice->status
        );
    }

    public function test_financial_ledger_rejects_unbalanced_or_foreign_building_entries(): void
    {
        $manager = $this->createUser(
            '09123334001',
            'ledger@example.test'
        );

        $a = $this->createStructure('LED-A');
        $b = $this->createStructure('LED-B');

        $role = $this->createRoleWithPermissions(
            'ledger-manager',
            ['financial-transactions.create']
        );

        $this->assignRole(
            $manager,
            $role,
            $a['building']
        );

        $cash = FinancialAccount::query()->create([
            'building_id' => $a['building']->id,
            'code' => 'CASH',
            'title' => 'Cash',
            'type' => FinancialAccountType::Cash->value,
            'currency' => 'IRR',
            'is_active' => true,
        ]);

        $income = FinancialAccount::query()->create([
            'building_id' => $a['building']->id,
            'code' => 'INC',
            'title' => 'Income',
            'type' => FinancialAccountType::Income->value,
            'currency' => 'IRR',
            'is_active' => true,
        ]);

        $foreign = FinancialAccount::query()->create([
            'building_id' => $b['building']->id,
            'code' => 'OTHER',
            'title' => 'Other',
            'type' => FinancialAccountType::Cash->value,
            'currency' => 'IRR',
            'is_active' => true,
        ]);

        Sanctum::actingAs($manager);

        $this->postJson(
            "/api/v1/buildings/{$a['building']->id}/financial-transactions",
            [
                'transaction_type' =>
                    FinancialTransactionType::Income->value,
                'entries' => [
                    [
                        'financial_account_id' => $cash->id,
                        'entry_type' => LedgerEntryType::Debit->value,
                        'amount' => 100000,
                    ],
                    [
                        'financial_account_id' => $income->id,
                        'entry_type' => LedgerEntryType::Credit->value,
                        'amount' => 100000,
                    ],
                ],
            ]
        )->assertCreated();

        $this->assertDatabaseCount(
            'financial_ledger_entries',
            2
        );

        $this->postJson(
            "/api/v1/buildings/{$a['building']->id}/financial-transactions",
            [
                'transaction_type' =>
                    FinancialTransactionType::Income->value,
                'entries' => [
                    [
                        'financial_account_id' => $cash->id,
                        'entry_type' => LedgerEntryType::Debit->value,
                        'amount' => 100000,
                    ],
                    [
                        'financial_account_id' => $income->id,
                        'entry_type' => LedgerEntryType::Credit->value,
                        'amount' => 90000,
                    ],
                ],
            ]
        )->assertUnprocessable();

        $this->postJson(
            "/api/v1/buildings/{$a['building']->id}/financial-transactions",
            [
                'transaction_type' =>
                    FinancialTransactionType::Income->value,
                'entries' => [
                    [
                        'financial_account_id' => $cash->id,
                        'entry_type' => LedgerEntryType::Debit->value,
                        'amount' => 100000,
                    ],
                    [
                        'financial_account_id' => $foreign->id,
                        'entry_type' => LedgerEntryType::Credit->value,
                        'amount' => 100000,
                    ],
                ],
            ]
        )->assertUnprocessable();
    }

    private function createIssuedInvoice(
        Building $building,
        Unit $unit,
        int $amount
    ): UnitInvoice {
        return UnitInvoice::query()->create([
            'building_id' => $building->id,
            'unit_id' => $unit->id,
            'invoice_number' => 'TEST-'.str()->uuid(),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'subtotal' => $amount,
            'discount_amount' => 0,
            'penalty_amount' => 0,
            'total_amount' => $amount,
            'paid_amount' => 0,
            'outstanding_amount' => $amount,
            'status' => InvoiceStatus::Issued->value,
        ]);
    }

    private function createUser(
        string $mobile,
        string $email
    ): User {
        return User::query()->create([
            'first_name' => 'Finance',
            'last_name' => 'User',
            'mobile' => $mobile,
            'email' => $email,
            'mobile_verified_at' => now(),
            'email_verified_at' => now(),
            'password' => 'TestPassword123!',
            'is_active' => true,
            'is_blocked' => false,
        ]);
    }

    private function createStructure(
        string $suffix,
        float $area = 100
    ): array {
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
            'title' => "Block {$suffix}",
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $floor = Floor::query()->create([
            'block_id' => $block->id,
            'floor_number' => 1,
            'title' => "Floor {$suffix}",
            'sort_order' => 1,
        ]);

        $unit = Unit::query()->create([
            'floor_id' => $floor->id,
            'unit_number' => "101-{$suffix}",
            'title' => "Unit {$suffix}",
            'area' => $area,
            'bedrooms' => 2,
            'usage_type' => UnitUsageType::cases()[0]->value,
            'is_active' => true,
        ]);

        return compact(
            'complex','building','block','floor','unit'
        );
    }

    private function createRoleWithPermissions(
        string $name,
        array $permissionNames
    ): Role {
        $role = Role::query()->create([
            'name' => $name,
            'display_name' => $name,
            'is_system' => true,
        ]);

        foreach ($permissionNames as $permissionName) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $permissionName],
                [
                    'display_name' => $permissionName,
                    'module' => explode('.', $permissionName)[0],
                ]
            );

            $role->permissions()->syncWithoutDetaching([
                $permission->id,
            ]);
        }

        return $role;
    }

    private function assignRole(
        User $user,
        Role $role,
        mixed $scope
    ): UserRoleAssignment {
        return UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => $scope->getMorphClass(),
            'scope_id' => $scope->getKey(),
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'is_active' => true,
            'assigned_by' => null,
        ]);
    }
}
