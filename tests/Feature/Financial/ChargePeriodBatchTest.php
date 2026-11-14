<?php
namespace Tests\Feature\Financial;

use App\Models\ChargePeriod;
use App\Services\ChargePeriodBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Morilog\Jalali\CalendarUtils;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

final class ChargePeriodBatchTest extends TestCase
{
    use RefreshDatabase, CreatesBuildingDomainData;

    private function date(int $year, int $month, int $day): string
    {
        return vsprintf('%04d-%02d-%02d', CalendarUtils::toGregorian($year, $month, $day));
    }

    public function test_month_lengths_due_rollover_and_atomic_creation(): void
    {
        $graph = $this->createBuildingGraph(); $actor = $this->createUser();
        $service = app(ChargePeriodBatchService::class);
        $data = ['year'=>1405, 'months'=>[12, 7, 6], 'period_start'=>$this->date(1405,6,1), 'period_end'=>$this->date(1405,6,31), 'due_date'=>$this->date(1405,7,5)];
        $plan = $service->preview($graph['building'], $data);
        $this->assertDatabaseCount('charge_periods', 0);
        $this->assertSame($this->date(1405,7,30), $plan[1]['period_end']);
        $this->assertSame($this->date(1405,12,29), $plan[2]['period_end']);
        $this->assertSame($this->date(1406,1,5), $plan[2]['due_date']);
        $this->assertCount(3, $service->create($graph['building'], $data, $actor));
        try { $service->create($graph['building'], $data, $actor); $this->fail('Duplicate accepted'); }
        catch (ValidationException) { $this->assertDatabaseCount('charge_periods', 3); }
        $other = $this->createBuildingGraph();
        $this->assertCount(3, $service->create($other['building'], $data, $actor));
    }

    public function test_conflict_in_last_month_does_not_save_earlier_months(): void
    {
        $graph = $this->createBuildingGraph(); $actor = $this->createUser();
        ChargePeriod::create(['building_id'=>$graph['building']->id,'title'=>'existing','period_start'=>$this->date(1405,12,1),'period_end'=>$this->date(1405,12,29),'due_date'=>$this->date(1406,1,5),'status'=>'draft']);
        $data = ['year'=>1405,'months'=>[6,7,8,9,10,11,12],'period_start'=>$this->date(1405,6,1),'period_end'=>$this->date(1405,6,31),'due_date'=>$this->date(1405,7,5)];
        try { app(ChargePeriodBatchService::class)->create($graph['building'], $data, $actor); $this->fail('Overlap accepted'); }
        catch (ValidationException) { $this->assertDatabaseCount('charge_periods', 1); }
    }

    public function test_leap_esfand_and_invalid_year_month_template(): void
    {
        $graph = $this->createBuildingGraph(); $service = app(ChargePeriodBatchService::class);
        $year = collect(range(1404,1410))->first(fn($year)=>CalendarUtils::isLeapJalaliYear($year));
        $data = ['year'=>$year,'months'=>[11,12],'period_start'=>$this->date($year,11,1),'period_end'=>$this->date($year,11,30),'due_date'=>$this->date($year,12,5)];
        $plan = $service->preview($graph['building'], $data);
        $this->assertSame($this->date($year,12,30), $plan[1]['period_end']);
        foreach ([['year'=>1403],['months'=>[]],['months'=>[11,11]],['months'=>[13]],['period_start'=>$this->date($year,10,1)]] as $invalid) {
            try { $service->preview($graph['building'], [...$data,...$invalid]); $this->fail('Invalid data accepted'); }
            catch (ValidationException) { $this->assertDatabaseCount('charge_periods',0); }
        }
    }
}
