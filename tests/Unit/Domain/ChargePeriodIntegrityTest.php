<?php

namespace Tests\Unit\Domain;

use App\Enums\ChargePeriodStatus;
use App\Models\BuildingExpense;
use App\Models\ChargeCalculation;
use App\Models\Unit;
use App\Models\UnitOccupancy;
use App\Models\UnitOwnership;
use App\Services\Charge\ResidentCountHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesChargePeriodScenario;
use Tests\TestCase;

final class ChargePeriodIntegrityTest extends TestCase
{
    use CreatesChargePeriodScenario, RefreshDatabase;

    private function rejects(callable $operation, string $field): void
    {
        try {
            $operation();
            $this->fail('Expected a validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_historical_count_edit_invalidates_review_even_when_unit_and_timestamps_do_not_change(): void
    {
        [$graph, , , $period, , $service] = $this->chargePeriodScenario();
        $token = $service->preview($period)['review_token'];
        DB::table('unit_resident_counts')->where('unit_id', $graph['unit']->id)->update(['resident_count' => 3]);
        $this->rejects(fn () => $service->calculate($period, $token), 'review_token');
        $this->assertSame(ChargePeriodStatus::Draft, $period->fresh()->status);
        $this->assertDatabaseCount('charge_calculations', 0);
    }

    public function test_resident_replacement_and_end_date_change_invalidate_review(): void
    {
        [, , $occupancy, $period, , $service] = $this->chargePeriodScenario();
        foreach (['user_id' => $this->createUser()->id, 'ends_at' => '2026-10-12'] as $key => $value) {
            $token = $service->preview($period)['review_token'];
            $occupancy->update([$key => $value]);
            $this->rejects(fn () => $service->calculate($period, $token), 'review_token');
        }
    }

    public function test_owner_replacement_invalidates_review(): void
    {
        [$graph, $actor, , $period, , $service] = $this->chargePeriodScenario();
        $owner = UnitOwnership::create([
            'unit_id' => $graph['unit']->id, 'user_id' => $actor->id,
            'starts_at' => '2026-10-01', 'is_active' => true, 'is_primary' => true,
        ]);
        $token = $service->preview($period)['review_token'];
        $owner->update(['user_id' => $this->createUser()->id]);
        $this->rejects(fn () => $service->calculate($period, $token), 'review_token');
    }

    public function test_duplicate_confirmation_is_rejected_even_with_frozen_time(): void
    {
        $this->freezeTime();
        [, , , $period, , $service] = $this->chargePeriodScenario();
        $stale = $period->fresh();
        $token = $service->preview($period)['review_token'];
        $service->calculate($period, $token);
        $this->rejects(fn () => $service->calculate($stale, $token), 'review_token');
        $this->assertSame(1, $period->fresh()->calculation_revision);
        $this->assertDatabaseCount('charge_calculations', 1);
        $service->calculate($period, $service->preview($period)['review_token']);
        $this->assertSame(2, $period->fresh()->calculation_revision);
        $this->assertDatabaseCount('charge_calculations', 1);
    }

    public function test_failed_recalculation_rolls_back_and_preserves_previous_result(): void
    {
        [, , , $period, $formula, $service] = $this->chargePeriodScenario();
        $service->calculate($period, $service->preview($period)['review_token']);
        $before = ChargeCalculation::first()->getAttributes();
        $hash = $period->fresh()->calculation_source_hash;
        // Failure occurs after old calculations are deleted and the new result is created.
        ChargeCalculation::created(function (): void {
            throw new \RuntimeException('Injected failure');
        });
        try {
            $service->calculate($period, $service->preview($period)['review_token']);
            $this->fail('Expected the injected error.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected failure', $exception->getMessage());
        } finally {
            ChargeCalculation::flushEventListeners();
        }
        $this->assertSame($before, ChargeCalculation::first()->getAttributes());
        $this->assertSame($hash, $period->fresh()->calculation_source_hash);
        $this->assertSame(ChargePeriodStatus::Calculated, $period->fresh()->status);
        $this->assertSame(1, $period->fresh()->calculation_revision);
    }

    public function test_source_changes_after_calculation_block_issue_until_review_and_recalculation(): void
    {
        [$graph, $actor, , $period, , $service] = $this->chargePeriodScenario();
        $service->calculate($period, $service->preview($period)['review_token']);
        app(ResidentCountHistory::class)->record($graph['unit'], 4, '2026-10-01');
        $this->rejects(fn () => $service->issue($period, $actor), 'calculation');
        $this->assertDatabaseCount('unit_invoices', 0);
        $service->calculate($period, $service->preview($period)['review_token']);
        $service->issue($period, $actor);
        $this->assertSame(ChargePeriodStatus::Issued, $period->fresh()->status);
        $this->assertDatabaseHas('unit_invoices', ['charge_period_id' => $period->id, 'total_amount' => 100000]);
    }

    public function test_stale_models_cannot_issue_twice_or_recalculate_an_issued_period(): void
    {
        [, $actor, , $period, , $service] = $this->chargePeriodScenario();
        $service->calculate($period, $service->preview($period)['review_token']);
        $stale = $period->fresh();
        $token = $service->preview($period)['review_token'];
        $service->issue($period, $actor);
        $this->rejects(fn () => $service->issue($stale, $actor), 'status');
        $this->rejects(fn () => $service->calculate($stale, $token), 'status');
        $this->assertDatabaseCount('unit_invoices', 1);
        $this->assertSame(ChargePeriodStatus::Issued, $period->fresh()->status);
    }

    public function test_legacy_calculation_without_fingerprint_requires_recalculation_before_issue(): void
    {
        [, $actor, , $period, , $service] = $this->chargePeriodScenario();
        $period->update(['status' => ChargePeriodStatus::Calculated]);
        $this->rejects(fn () => $service->issue($period, $actor), 'calculation');
        $this->assertDatabaseCount('unit_invoices', 0);
    }

    public function test_another_buildings_resident_changes_do_not_invalidate_review(): void
    {
        [, , , $period, , $service] = $this->chargePeriodScenario();
        $token = $service->preview($period)['review_token'];
        $other = $this->createBuildingGraph();
        app(ResidentCountHistory::class)->record($other['unit'], 8, '2026-10-01');
        $service->calculate($period, $token);
        $this->assertSame(ChargePeriodStatus::Calculated, $period->fresh()->status);
    }

    public function test_recalculation_uses_new_headcounts_and_new_resident_for_invoice_payer(): void
    {
        [$graph, $actor, $occupancy, $period, $formula, $service] = $this->chargePeriodScenario();
        $formula->update(['calculation_type' => 'persons']);
        $formula->chargeItems()->update(['base_amount' => 0]);
        $secondUser = $this->createUser();
        $replacement = $this->createUser();
        $second = Unit::create([
            'floor_id' => $graph['floor']->id, 'unit_number' => 'SECOND',
            'area' => 80, 'usage_type' => 'residential', 'is_active' => true,
        ]);
        UnitOccupancy::create([
            'unit_id' => $second->id, 'user_id' => $secondUser->id,
            'occupancy_type' => 'tenant', 'starts_at' => '2026-10-01',
            'is_primary' => true, 'is_active' => true,
        ]);
        app(ResidentCountHistory::class)->record($second, 1, '2026-10-01');
        BuildingExpense::create([
            'building_id' => $graph['building']->id, 'title' => 'Water',
            'financial_category_id' => $formula->chargeItems()->first()->financial_category_id,
            'amount' => 300000, 'expense_date' => '2026-10-01',
            'status' => 'posted', 'payer_responsibility' => 'resident',
        ]);
        $service->calculate($period, $service->preview($period)['review_token']);
        $this->assertDatabaseHas('charge_expense_allocations', [
            'charge_period_id' => $period->id, 'unit_id' => $graph['unit']->id,
            'allocated_amount' => 200000, 'payer_user_id' => $actor->id,
        ]);
        app(ResidentCountHistory::class)->record($graph['unit'], 1, '2026-10-01');
        $occupancy->update(['user_id' => $replacement->id]);
        $this->rejects(fn () => $service->issue($period, $actor), 'calculation');
        $service->calculate($period, $service->preview($period)['review_token']);
        $service->issue($period, $actor);
        $this->assertDatabaseCount('unit_invoices', 2);
        $this->assertDatabaseHas('unit_invoices', [
            'charge_period_id' => $period->id, 'unit_id' => $graph['unit']->id,
            'payer_user_id' => $replacement->id, 'total_amount' => 150000,
        ]);
        $this->assertDatabaseHas('unit_invoices', [
            'charge_period_id' => $period->id, 'unit_id' => $second->id,
            'payer_user_id' => $secondUser->id, 'total_amount' => 150000,
        ]);
        $this->assertDatabaseMissing('unit_invoices', [
            'charge_period_id' => $period->id, 'payer_user_id' => $actor->id,
        ]);
    }
}
