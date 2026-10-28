<?php

namespace Tests\Unit\Domain;

use App\Enums\ExpensePayerResponsibility;
use App\Models\ChargePeriod;
use App\Models\UnitOccupancy;
use App\Models\UnitOwnership;
use App\Services\Charge\ExpensePayerResolver;
use App\Support\Jalali\JalaliDateFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class ExpensePayerPeriodTest extends TestCase
{
    use RefreshDatabase, CreatesBuildingDomainData;

    public function test_future_resident_and_owner_do_not_pay_a_previous_period(): void
    {
        $graph = $this->createBuildingGraph();
        $graph['unit']->update(['unit_number' => '21']);
        $user = $this->createUser();
        $period = $this->period($graph['building']->id);

        $occupancy = UnitOccupancy::query()->create([
            'unit_id' => $graph['unit']->id,
            'user_id' => $user->id,
            'occupancy_type' => 'owner',
            'starts_at' => '2026-10-26',
            'is_primary' => true,
            'is_active' => true,
        ]);
        UnitOwnership::query()->create([
            'unit_id' => $graph['unit']->id,
            'user_id' => $user->id,
            'ownership_percentage' => 100,
            'starts_at' => '2026-10-25',
            'is_primary' => true,
            'is_active' => true,
        ]);

        try {
            app(ExpensePayerResolver::class)->resolve(
                $graph['unit'], ExpensePayerResponsibility::Resident, $period
            );
            $this->fail('A future relationship must not receive historical charges.');
        } catch (ValidationException $exception) {
            $message = $exception->errors()['payer_responsibility'][0];
            $this->assertStringContainsString('واحد 21', $message);
            $this->assertStringContainsString($graph['building']->title, $message);
            $dates = new JalaliDateFormatter();
            $this->assertStringContainsString($dates->date($period->period_start), $message);
            $this->assertStringContainsString($dates->date($period->period_end), $message);
            $this->assertStringContainsString('تاریخ واقعی شروع', $message);
        }

        // Correcting the actual history, rather than ignoring dates, resolves the failure.
        $occupancy->update(['starts_at' => '2026-09-23']);
        $payer = app(ExpensePayerResolver::class)->resolve(
            $graph['unit'], ExpensePayerResponsibility::Resident, $period
        );
        $this->assertTrue($user->is($payer));
    }

    public function test_ended_historical_resident_is_used_instead_of_current_resident(): void
    {
        $graph = $this->createBuildingGraph();
        $historical = $this->createUser();
        $current = $this->createUser();
        $period = $this->period($graph['building']->id);

        UnitOccupancy::query()->create([
            'unit_id' => $graph['unit']->id,
            'user_id' => $historical->id,
            'occupancy_type' => 'tenant',
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-09-23',
            'is_primary' => true,
            'is_active' => false,
        ]);
        UnitOccupancy::query()->create([
            'unit_id' => $graph['unit']->id,
            'user_id' => $current->id,
            'occupancy_type' => 'tenant',
            'starts_at' => '2026-10-23',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $payer = app(ExpensePayerResolver::class)->resolve(
            $graph['unit'], ExpensePayerResponsibility::Resident, $period
        );
        $this->assertTrue($historical->is($payer));
    }

    public function test_same_unit_number_in_another_building_does_not_supply_the_payer(): void
    {
        $graph = $this->createBuildingGraph();
        $other = $this->createBuildingGraph();
        $other['unit']->update(['unit_number' => $graph['unit']->unit_number]);
        UnitOccupancy::query()->create([
            'unit_id' => $other['unit']->id,
            'user_id' => $this->createUser()->id,
            'occupancy_type' => 'tenant',
            'starts_at' => '2026-01-01',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);
        app(ExpensePayerResolver::class)->resolve(
            $graph['unit'],
            ExpensePayerResponsibility::Resident,
            $this->period($graph['building']->id)
        );
    }

    private function period(int $buildingId): ChargePeriod
    {
        return ChargePeriod::query()->create([
            'building_id' => $buildingId,
            'title' => 'Mehr 1405',
            'period_start' => '2026-09-23',
            'period_end' => '2026-10-22',
            'due_date' => '2026-10-23',
            'status' => 'draft',
        ]);
    }
}
