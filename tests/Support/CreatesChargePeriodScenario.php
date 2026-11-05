<?php

namespace Tests\Support;

use App\Models\ChargeFormula;
use App\Models\ChargePeriod;
use App\Models\FinancialCategory;
use App\Models\UnitOccupancy;
use App\Services\Charge\ResidentCountHistory;
use App\Services\ChargePeriodService;

trait CreatesChargePeriodScenario
{
    use CreatesBuildingDomainData;

    protected function chargePeriodScenario(): array
    {
        $graph = $this->createBuildingGraph();
        $actor = $this->createUser();
        $occupancy = UnitOccupancy::create([
            'unit_id' => $graph['unit']->id, 'user_id' => $actor->id,
            'occupancy_type' => 'tenant', 'starts_at' => '2026-10-01',
            'is_primary' => true, 'is_active' => true,
        ]);
        app(ResidentCountHistory::class)->record($graph['unit'], 2, '2026-10-01');
        $period = ChargePeriod::create([
            'building_id' => $graph['building']->id, 'title' => 'October',
            'period_start' => '2026-10-01', 'period_end' => '2026-10-31',
            'due_date' => '2026-11-10', 'status' => 'draft',
        ]);
        $category = FinancialCategory::create(['title' => 'Fixed', 'type' => 'expense', 'is_active' => true]);
        $formula = ChargeFormula::create([
            'building_id' => $graph['building']->id, 'title' => 'Fixed',
            'calculation_type' => 'fixed', 'is_active' => true,
        ]);
        $formula->chargeItems()->create(['title' => 'Fixed', 'financial_category_id' => $category->id, 'base_amount' => 100000]);

        return [$graph, $actor, $occupancy, $period, $formula, app(ChargePeriodService::class)];
    }
}
