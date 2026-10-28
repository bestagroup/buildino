<?php

namespace Tests\Unit\Domain;

use App\Enums\ChargeCalculationType;
use App\Models\BuildingExpense;
use App\Models\ChargeFormula;
use App\Models\ChargePeriod;
use App\Models\FinancialCategory;
use App\Models\Floor;
use App\Models\Unit;
use App\Models\UnitOccupancy;
use App\Models\BuildingExpenseAllocationRule;
use App\Enums\ExpenseAllocationMethod;
use App\Services\ChargeService;
use App\Services\Charge\ExpenseAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class ChargeFormulaAllocationServiceTest extends TestCase
{
    use RefreshDatabase, CreatesBuildingDomainData;

    public function test_period_expense_can_be_allocated_by_equal_persons_and_floor_coefficient(): void
    {
        $graph = $this->createBuildingGraph();

        $secondFloor = Floor::query()->create([
            'block_id' => $graph['block']->id,
            'floor_number' => 2,
            'title' => 'Second',
        ]);

        $secondUnit = Unit::query()->create([
            'floor_id' => $secondFloor->id,
            'unit_number' => '202',
            'area' => 50,
            'usage_type' => 'residential',
            'is_active' => true,
        ]);

        $period = ChargePeriod::query()->create([
            'building_id' => $graph['building']->id,
            'title' => 'August',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'due_date' => '2026-09-10',
            'status' => 'draft',
        ]);

        $units = collect([
            $graph['unit']->fresh('floor'),
            $secondUnit->fresh('floor'),
        ]);

        $service = app(
            ExpenseAllocationService::class
        );

        $equal = $this->formula(
            $graph['building']->id,
            ChargeCalculationType::Equal,
            []
        );

        $equalExpense = $this->expense(
            $graph['building']->id,
            300000
        );

        $equalRows = $service->allocateByFormula(
            $equalExpense,
            $equal,
            $period,
            $units
        );

        $this->assertSame(
            [150000, 150000],
            array_column($equalRows, 'amount')
        );

        $graph['unit']->update([
            'resident_count' => 3,
        ]);

        $secondUnit->update([
            'resident_count' => 1,
        ]);

        $units = collect([
            $graph['unit']->fresh('floor'),
            $secondUnit->fresh('floor'),
        ]);

        foreach ([$graph['unit'], $secondUnit] as $occupiedUnit) {
            $this->occupy($occupiedUnit, '2026-08-01');
        }

        $persons = $this->formula(
            $graph['building']->id,
            ChargeCalculationType::Persons,
            []
        );

        $personExpense = $this->expense(
            $graph['building']->id,
            400000
        );

        $personRows = $service->allocateByFormula(
            $personExpense,
            $persons,
            $period,
            $units
        );

        $this->assertSame(
            [300000, 100000],
            array_column($personRows, 'amount')
        );

        $floor = $this->formula(
            $graph['building']->id,
            ChargeCalculationType::FloorCoefficient,
            [
                'floor_coefficients' => [
                    '1' => 1,
                    '2' => 2,
                ],
            ]
        );

        $floorExpense = $this->expense(
            $graph['building']->id,
            300000
        );

        $floorRows = $service->allocateByFormula(
            $floorExpense,
            $floor,
            $period,
            $units
        );

        $this->assertSame(
            [100000, 200000],
            array_column($floorRows, 'amount')
        );
    }


    public function test_vacant_units_are_excluded_from_both_persons_allocation_paths(): void
    {
        $graph = $this->createBuildingGraph();
        $occupied = $graph['unit'];
        $occupied->update(['resident_count' => 3]);
        $vacant = $occupied->replicate();
        $vacant->unit_number = 'VACANT';
        $vacant->resident_count = 10;
        $vacant->save();
        // Historical residency must count even when it has since ended.
        $this->occupy($occupied, '2026-01-01', '2026-08-31');
        $this->occupy($vacant, '2026-09-01');

        $period = new ChargePeriod([
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
        ]);
        $formula = $this->formula($graph['building']->id, ChargeCalculationType::Persons, []);
        $expense = $this->expense($graph['building']->id, 101);
        $rule = new BuildingExpenseAllocationRule([
            'allocation_method' => ExpenseAllocationMethod::Persons,
        ]);
        $allocator = app(ExpenseAllocationService::class);
        $units = collect([$occupied, $vacant]);

        foreach ([
            $allocator->allocateByFormula($expense, $formula, $period, $units),
            $allocator->allocate($expense, $rule, $period, $units),
        ] as $rows) {
            $this->assertCount(1, $rows);
            $row = array_values($rows)[0];
            $this->assertTrue($occupied->is($row['unit']));
            $this->assertSame(101, $row['amount']);
            $this->assertSame(3.0, $row['base_value']);
        }

        $this->assertSame([], $allocator->allocateByFormula(
            $expense, $formula, $period, collect([$vacant])
        ));
        $this->assertSame([], $allocator->allocate(
            $expense, $rule, $period, collect([$vacant])
        ));

        $breakdown = app(ChargeService::class)->calculateBreakdown(
            $formula, $vacant, $period->period_start, $period->period_end
        );
        $this->assertSame(0, $breakdown['base_value']);
        $this->assertSame(0, $breakdown['amount']);
    }

    public function test_persons_eligibility_uses_inclusive_period_dates_and_excludes_deleted_history(): void
    {
        $graph = $this->createBuildingGraph();
        $unit = $graph['unit'];
        $unit->update(['resident_count' => 2]);
        $period = new ChargePeriod([
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
        ]);
        $counter = new \App\Services\Charge\PeriodResidentCount();
        $count = fn () => $counter->count($unit, $period->period_start, $period->period_end);
        $this->assertSame(0, $count());
        $history = $this->occupy($unit, '2026-01-01', '2026-07-31');
        $this->assertSame(0, $count());
        $history->update(['ends_at' => '2026-08-01']);
        $this->assertSame(2, $count());
        $history->update(['starts_at' => '2026-08-31', 'ends_at' => null]);
        $this->assertSame(2, $count());
        $history->delete();
        $this->assertSame(0, $count());
    }

    private function occupy(Unit $unit, string $start, ?string $end = null): UnitOccupancy
    {
        return UnitOccupancy::query()->create([
            'unit_id' => $unit->id,
            'user_id' => $this->createUser()->id,
            'occupancy_type' => 'resident',
            'starts_at' => $start,
            'ends_at' => $end,
            'is_primary' => true,
            'is_active' => $end === null,
        ]);
    }

    private function formula(
        int $buildingId,
        ChargeCalculationType $type,
        array $configuration
    ): ChargeFormula {
        return ChargeFormula::query()->create([
            'building_id' => $buildingId,
            'title' => $type->value,
            'calculation_type' => $type,
            'configuration' => $configuration,
            'is_active' => true,
        ]);
    }

    private function expense(
        int $buildingId,
        int $amount
    ): BuildingExpense {
        $category = FinancialCategory::query()->create([
            'building_id' => $buildingId,
            'title' => 'Expense '.uniqid(),
            'type' => 'expense',
            'is_active' => true,
        ]);

        return BuildingExpense::query()->create([
            'building_id' => $buildingId,
            'financial_category_id' => $category->id,
            'title' => 'Expense '.uniqid(),
            'amount' => $amount,
            'expense_date' => '2026-08-15',
            'status' => 'posted',
        ]);
    }


}
