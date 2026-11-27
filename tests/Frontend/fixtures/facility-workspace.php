<?php

use App\Models\BuildingFacility;
use App\Models\UnitOwnership;
use App\Models\User;
use Database\Seeders\AccessScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

putenv('APP_ENV=testing');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
putenv('SESSION_DRIVER=array');
putenv('CACHE_STORE=array');
require __DIR__.'/../../../vendor/autoload.php';

final class FacilityWorkspaceFixture extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = parent::createApplication();
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('cache.default', 'array');
        return $app;
    }

    public function renderPages(): array
    {
        $this->setUp();
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') throw new RuntimeException('Isolated database required.');
        $this->seed(AccessScenarioSeeder::class);
        URL::forceRootUrl('http://localhost');
        $manager = User::where('email', 'role.building@buildino.local')->firstOrFail();
        $owner = User::where('email', 'role.owner@buildino.local')->firstOrFail();
        $building = UnitOwnership::where('user_id', $owner->id)->firstOrFail()->unit->floor->block->building;
        $this->actingAs($manager);
        $facility = BuildingFacility::create(['building_id'=>$building->id, 'title'=>'سالن اجتماعات', 'code'=>'QA-HALL', 'type'=>'meeting_hall', 'capacity'=>5, 'default_price'=>10000, 'requires_payment'=>true, 'is_active'=>false]);
        $this->postJson('/api/v1/facilities/'.$facility->id.'/schedule-batches', ['days_of_week'=>[6,0,1,2,3,4,5], 'start_time'=>'08:00', 'end_time'=>'22:00', 'slot_duration_minutes'=>60, 'capacity'=>5, 'price'=>10000])->assertCreated();
        $facility->facilityReservationRules()->create(['auto_confirm'=>true, 'max_duration_minutes'=>60]);
        $pages['facility'] = $this->getJson('/api/v1/facilities/'.$facility->id)->assertOk()->json('data');
        $pages['facilities'] = ['data'=>[$pages['facility']], 'meta'=>['current_page'=>1,'last_page'=>1,'total'=>1]];
        $pages['buildingId'] = $building->id;
        foreach (['configuration'=>'facilities','reservations'=>'reservations'] as $key=>$resource) $pages[$key] = $this->get('/management/operations/'.$resource.'?building_id='.$building->id)->assertOk()->getContent();
        return $pages;
    }
}
echo json_encode((new FacilityWorkspaceFixture('renderPages'))->renderPages(), JSON_THROW_ON_ERROR);
