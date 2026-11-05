<?php

namespace Tests\Unit\Domain;

use App\Models\BuildingExpense;
use App\Models\ChargeFormula;
use App\Models\ChargePeriod;
use App\Models\FinancialCategory;
use App\Services\ChargePeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

final class ChargePeriodPreviewTest extends TestCase
{
    use RefreshDatabase, CreatesBuildingDomainData;

    public function test_preview_is_read_only_and_uses_period_building_and_posting_filters(): void
    {
        $graph = $this->createBuildingGraph();
        $other = $this->createBuildingGraph();
        $period = ChargePeriod::create([
            'building_id' => $graph['building']->id, 'title' => 'Review',
            'period_start' => '2026-10-01', 'period_end' => '2026-10-31',
            'due_date' => '2026-11-10', 'status' => 'draft',
        ]);
        foreach (['fixed' => 100000, 'equal' => 0] as $type => $amount) {
            $category = FinancialCategory::create(['title' => $type, 'type' => 'expense', 'is_active' => true]);
            $formula = ChargeFormula::create([
                'building_id' => $graph['building']->id, 'title' => $type,
                'calculation_type' => $type, 'is_active' => true,
            ]);
            $formula->chargeItems()->create(['title' => $type, 'financial_category_id' => $category->id, 'base_amount' => $amount]);
        }
        $base = ['building_id' => $graph['building']->id, 'title' => 'Water', 'amount' => 50000, 'expense_date' => '2026-10-01', 'financial_category_id' => $category->id];
        $bill = BuildingExpense::create([...$base, 'status' => 'posted', 'payment_status' => 'unpaid']);
        BuildingExpense::create([...$base, 'title' => 'Draft bill', 'status' => 'draft']);
        BuildingExpense::create([...$base, 'expense_date' => '2026-09-30', 'status' => 'posted']);
        BuildingExpense::create([...$base, 'building_id' => $other['building']->id, 'status' => 'posted']);
        $service = app(ChargePeriodService::class);
        $result = $service->preview($period);
        $this->assertSame([], $result['errors']);
        $this->assertCount(1, $result['expenses']);
        $this->assertCount(1, $result['excluded_expenses']);
        $this->assertSame(50000, $result['expense_total']);
        $this->assertSame(100000, $result['fixed_total']);
        $this->assertSame('unpaid', $result['expenses'][0]['payment_status']);
        $this->assertSame($result['review_token'], $service->preview($period)['review_token']);
        $bill->update(['amount' => 60000]);
        $this->assertNotSame($result['review_token'], $service->preview($period)['review_token']);
        $this->assertSame('draft', $period->fresh()->status->value);
        $this->assertDatabaseCount('charge_calculations', 0);
        $this->assertDatabaseCount('charge_expense_allocations', 0);
        $this->assertDatabaseCount('unit_invoices', 0);
    }
}
