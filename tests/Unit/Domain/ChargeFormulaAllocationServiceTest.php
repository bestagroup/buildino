<?php

namespace Tests\Unit\Domain;

use App\Enums\ChargeCalculationType;
use App\Models\BuildingExpense;
use App\Models\ChargeFormula;
use App\Models\ChargePeriod;
use App\Models\FinancialCategory;
use App\Models\Floor;
use App\Models\Unit;
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
