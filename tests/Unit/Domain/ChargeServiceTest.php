<?php

namespace Tests\Unit\Domain;

use App\Enums\ChargeCalculationType;
use App\Models\ChargeFormula;
use App\Services\ChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class ChargeServiceTest extends TestCase
{
    use RefreshDatabase, CreatesBuildingDomainData;

    public function test_fixed_charge_returns_base_amount(): void
    {
        $graph = $this->createBuildingGraph();

        $formula = ChargeFormula::query()->create([
            'building_id' => $graph['building']->id,
            'title' => 'Fixed',
            'calculation_type' => ChargeCalculationType::Fixed,
            'configuration' => ['base_amount' => 500000],
            'is_active' => true,
        ]);

        $amount = app(ChargeService::class)->calculate($formula, $graph['unit']);

        $this->assertSame(500000, $amount);
    }

    public function test_area_charge_multiplies_rate_by_unit_area(): void
    {
        $graph = $this->createBuildingGraph();
        $graph['unit']->update(['area' => 125]);

        $formula = ChargeFormula::query()->create([
            'building_id' => $graph['building']->id,
            'title' => 'Area',
            'calculation_type' => ChargeCalculationType::Area,
            'configuration' => ['base_amount' => 10000],
            'is_active' => true,
        ]);

        $amount = app(ChargeService::class)->calculate($formula, $graph['unit']);

        $this->assertSame(1250000, $amount);
    }
}
