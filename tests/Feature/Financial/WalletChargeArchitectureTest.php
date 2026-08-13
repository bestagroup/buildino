<?php

namespace Tests\Feature\Financial;

use App\Enums\ChargePolicyMode;
use App\Enums\ExpenseAllocationMethod;
use App\Enums\InvoiceStatus;
use App\Enums\OccupancyType;
use App\Enums\UnitChargePayerSource;
use App\Enums\UnitUsageType;
use App\Enums\WalletTransferType;
use App\Models\Block;
use App\Models\Building;
use App\Models\BuildingChargePolicy;
use App\Models\BuildingExpense;
use App\Models\BuildingExpenseAllocationRule;
use App\Models\ChargePeriod;
use App\Models\Complex;
use App\Models\FinancialCategory;
use App\Models\Floor;
use App\Models\Unit;
use App\Models\UnitInvoice;
use App\Models\UnitOccupancy;
use App\Models\User;
use App\Services\Charge\UnitChargePayerService;
use App\Services\Charge\WalletChargePeriodService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WalletChargeArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_unit_and_building_wallets_are_independent(): void
    {
        $user = $this->createUser(
            '09124440001',
            'wallet-user@example.test'
        );

        $graph = $this->createBuildingWithUnits(
            'WALLET',
            [
                ['number' => '101', 'area' => 100],
            ]
        );

        $wallets = app(WalletService::class);

        $userWallet = $wallets->walletFor($user);
        $unitWallet = $wallets->walletFor($graph['units'][0]);
        $buildingWallet = $wallets->walletFor($graph['building']);

        $this->assertNotSame(
            $userWallet->id,
            $unitWallet->id
        );

        $this->assertNotSame(
            $unitWallet->id,
            $buildingWallet->id
        );

        $wallets->credit(
            $userWallet,
            1_000_000,
            WalletTransferType::TopUp,
            'test:user-topup'
        );

        $this->assertSame(
            1_000_000,
            (int) $userWallet->fresh()->balance
        );

        $this->assertSame(
            0,
            (int) $unitWallet->fresh()->balance
        );

        $this->assertSame(
            0,
            (int) $buildingWallet->fresh()->balance
        );
    }

    public function test_water_can_be_split_by_persons_and_gas_by_area_in_same_period(): void
    {
        $manager = $this->createUser(
            '09124441001',
            'allocation-manager@example.test'
        );

        $graph = $this->createBuildingWithUnits(
            'ALLOC',
            [
                ['number' => '101', 'area' => 100],
                ['number' => '102', 'area' => 50],
            ]
        );

        $unitA = $graph['units'][0];
        $unitB = $graph['units'][1];

        /*
         * Unit A: 3 residents
         * Unit B: 1 resident
         */
        foreach (range(1, 3) as $i) {
            $resident = $this->createUser(
                '09124443'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                "resident-a-{$i}@example.test"
            );

            $this->occupy(
                $unitA,
                $resident
            );
        }

        $residentB = $this->createUser(
            '09124441999',
            'resident-b@example.test'
        );

        $this->occupy(
            $unitB,
            $residentB
        );

        $water = FinancialCategory::query()->create([
            'building_id' => $graph['building']->id,
            'title' => 'Water',
            'type' => 'expense',
            'is_active' => true,
        ]);

        $gas = FinancialCategory::query()->create([
            'building_id' => $graph['building']->id,
            'title' => 'Gas',
            'type' => 'expense',
            'is_active' => true,
        ]);

        BuildingChargePolicy::query()->create([
            'building_id' => $graph['building']->id,
            'mode' => ChargePolicyMode::SharedExpenses,
            'fixed_monthly_amount' => 0,
            'auto_collect' => false,
            'allow_partial' => true,
            'is_active' => true,
        ]);

        BuildingExpenseAllocationRule::query()->create([
            'building_id' => $graph['building']->id,
            'financial_category_id' => $water->id,
            'allocation_method' => ExpenseAllocationMethod::Persons,
            'is_active' => true,
        ]);

        BuildingExpenseAllocationRule::query()->create([
            'building_id' => $graph['building']->id,
            'financial_category_id' => $gas->id,
            'allocation_method' => ExpenseAllocationMethod::Area,
            'is_active' => true,
        ]);

        BuildingExpense::query()->create([
            'building_id' => $graph['building']->id,
            'financial_category_id' => $water->id,
            'title' => 'Shared water bill',
            'amount' => 400_000,
            'expense_date' => '2026-08-10',
            'status' => 'posted',
            'created_by' => $manager->id,
            'approved_by' => $manager->id,
            'approved_at' => now(),
            'posted_at' => now(),
        ]);

        BuildingExpense::query()->create([
            'building_id' => $graph['building']->id,
            'financial_category_id' => $gas->id,
            'title' => 'Shared gas bill',
            'amount' => 300_000,
            'expense_date' => '2026-08-11',
            'status' => 'posted',
            'created_by' => $manager->id,
            'approved_by' => $manager->id,
            'approved_at' => now(),
            'posted_at' => now(),
        ]);

        $period = ChargePeriod::query()->create([
            'building_id' => $graph['building']->id,
            'title' => 'August 2026',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'due_date' => '2026-09-10',
            'status' => 'draft',
            'created_by' => $manager->id,
        ]);

        app(WalletChargePeriodService::class)
            ->calculate(
                $period,
                $manager
            );

        /*
         * Water:
         *   A = 3 / 4 = 300,000
         *   B = 1 / 4 = 100,000
         *
         * Gas by area 100 : 50:
         *   A = 200,000
         *   B = 100,000
         *
         * Totals:
         *   A = 500,000
         *   B = 200,000
         */
        $invoiceA = UnitInvoice::query()
            ->where('charge_period_id', $period->id)
            ->where('unit_id', $unitA->id)
            ->firstOrFail();

        $invoiceB = UnitInvoice::query()
            ->where('charge_period_id', $period->id)
            ->where('unit_id', $unitB->id)
            ->firstOrFail();

        $this->assertSame(
            500_000,
            (int) $invoiceA->total_amount
        );

        $this->assertSame(
            200_000,
            (int) $invoiceB->total_amount
        );

        $this->assertDatabaseHas(
            'charge_expense_allocations',
            [
                'building_expense_id' => 1,
                'unit_id' => $unitA->id,
                'allocated_amount' => 300_000,
            ]
        );

        $this->assertDatabaseHas(
            'charge_expense_allocations',
            [
                'building_expense_id' => 2,
                'unit_id' => $unitA->id,
                'allocated_amount' => 200_000,
            ]
        );

        $this->assertSame(
            700_000,
            (int) UnitInvoice::query()
                ->where('charge_period_id', $period->id)
                ->sum('total_amount')
        );

        $this->assertSame(
            700_000,
            (int) BuildingExpense::query()
                ->where('building_id', $graph['building']->id)
                ->where('status', 'posted')
                ->sum('amount')
        );
    }

    public function test_charge_is_collected_from_configured_wallet_into_building_wallet(): void
    {
        $manager = $this->createUser(
            '09124442001',
            'collection-manager@example.test'
        );

        $payer = $this->createUser(
            '09124442002',
            'payer@example.test'
        );

        $unrelated = $this->createUser(
            '09124442003',
            'unrelated@example.test'
        );

        $graph = $this->createBuildingWithUnits(
            'COLLECT',
            [
                ['number' => '101', 'area' => 100],
                ['number' => '102', 'area' => 100],
            ]
        );

        $unitA = $graph['units'][0];
        $unitB = $graph['units'][1];

        $this->occupy(
            $unitB,
            $payer
        );

        $payerService = app(
            UnitChargePayerService::class
        );

        $payerService->configure(
            $unitA,
            UnitChargePayerSource::UnitWallet,
            null,
            true,
            true
        );

        $payerService->configure(
            $unitB,
            UnitChargePayerSource::UserWallet,
            $payer,
            true,
            true
        );

        try {
            $payerService->configure(
                $unitB,
                UnitChargePayerSource::UserWallet,
                $unrelated
            );

            $this->fail(
                'Unrelated user should not be accepted as unit payer.'
            );
        } catch (ValidationException $e) {
            $this->assertArrayHasKey(
                'payer_user_id',
                $e->errors()
            );
        }

        BuildingChargePolicy::query()->create([
            'building_id' => $graph['building']->id,
            'mode' => ChargePolicyMode::Fixed,
            'fixed_monthly_amount' => 300_000,
            'auto_collect' => true,
            'allow_partial' => true,
            'is_active' => true,
        ]);

        $period = ChargePeriod::query()->create([
            'building_id' => $graph['building']->id,
            'title' => 'Fixed August charge',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'due_date' => '2026-09-10',
            'status' => 'draft',
            'created_by' => $manager->id,
        ]);

        $wallets = app(WalletService::class);

        $unitAWallet = $wallets->walletFor($unitA);
        $unitBWallet = $wallets->walletFor($unitB);
        $payerWallet = $wallets->walletFor($payer);
        $buildingWallet = $wallets->walletFor(
            $graph['building']
        );

        $wallets->credit(
            $unitAWallet,
            500_000,
            WalletTransferType::TopUp,
            'test:unit-a-topup'
        );

        $wallets->credit(
            $payerWallet,
            200_000,
            WalletTransferType::TopUp,
            'test:payer-topup'
        );

        $service = app(
            WalletChargePeriodService::class
        );

        $service->calculate(
            $period,
            $manager
        );

        $service->issueAndCollect(
            $period->fresh(),
            $manager
        );

        $invoiceA = UnitInvoice::query()
            ->where('charge_period_id', $period->id)
            ->where('unit_id', $unitA->id)
            ->firstOrFail();

        $invoiceB = UnitInvoice::query()
            ->where('charge_period_id', $period->id)
            ->where('unit_id', $unitB->id)
            ->firstOrFail();

        /*
         * Unit A pays fully from its Unit Wallet.
         */
        $this->assertSame(
            InvoiceStatus::Paid,
            $invoiceA->status
        );

        $this->assertSame(
            200_000,
            (int) $unitAWallet->fresh()->balance
        );

        /*
         * Unit B uses the user's personal wallet.
         * It has only 200,000, so a partial payment is collected.
         */
        $this->assertSame(
            InvoiceStatus::Partial,
            $invoiceB->status
        );

        $this->assertSame(
            200_000,
            (int) $invoiceB->paid_amount
        );

        $this->assertSame(
            100_000,
            (int) $invoiceB->outstanding_amount
        );

        $this->assertSame(
            0,
            (int) $payerWallet->fresh()->balance
        );

        /*
         * Unit B wallet is a different wallet and must remain untouched.
         */
        $this->assertSame(
            0,
            (int) $unitBWallet->fresh()->balance
        );

        /*
         * Building receives 300,000 + 200,000.
         */
        $this->assertSame(
            500_000,
            (int) $buildingWallet->fresh()->balance
        );

        $this->assertDatabaseCount(
            'invoice_wallet_settlements',
            2
        );
    }

    private function occupy(
        Unit $unit,
        User $user
    ): UnitOccupancy {
        return UnitOccupancy::query()->create([
            'unit_id' => $unit->id,
            'user_id' => $user->id,
            'occupancy_type' => OccupancyType::Resident->value,
            'starts_at' => '2026-01-01',
            'ends_at' => null,
            'is_primary' => true,
            'is_active' => true,
        ]);
    }

    private function createUser(
        string $mobile,
        string $email
    ): User {
        return User::query()->create([
            'first_name' => 'Wallet',
            'last_name' => 'Tester',
            'mobile' => $mobile,
            'email' => $email,
            'mobile_verified_at' => now(),
            'email_verified_at' => now(),
            'password' => 'TestPassword123!',
            'is_active' => true,
            'is_blocked' => false,
        ]);
    }

    private function createBuildingWithUnits(
        string $suffix,
        array $units
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

        $models = [];

        foreach ($units as $unitData) {
            $models[] = Unit::query()->create([
                'floor_id' => $floor->id,
                'unit_number' => $unitData['number'],
                'title' => "Unit {$unitData['number']}",
                'area' => $unitData['area'],
                'bedrooms' => 2,
                'usage_type' => UnitUsageType::cases()[0]->value,
                'is_active' => true,
            ]);
        }

        return [
            'complex' => $complex,
            'building' => $building,
            'block' => $block,
            'floor' => $floor,
            'units' => $models,
        ];
    }
}
