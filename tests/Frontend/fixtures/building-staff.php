<?php

use App\Models\BuildingStaff;
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

final class BuildingStaffFixture extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = parent::createApplication();
        $app['config']->set('database.default','sqlite');
        $app['config']->set('database.connections.sqlite.database',':memory:');
        $app['config']->set('session.driver','array');
        $app['config']->set('cache.default','array');
        return $app;
    }

    public function renderPages(): array
    {
        $this->setUp();
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') throw new RuntimeException('Isolated database required.');
        $this->seed(AccessScenarioSeeder::class);
        URL::forceRootUrl('http://localhost');
        $manager = User::where('email','role.building@buildino.local')->firstOrFail();
        $owner = User::where('email','role.owner@buildino.local')->firstOrFail();
        $building = UnitOwnership::where('user_id',$owner->id)->firstOrFail()->unit->floor->block->building;
        $this->actingAs($manager);
        $url = '/management/operations/building-staff?building_id='.$building->id;
        $pages['empty'] = $this->get($url)->assertOk()->getContent();
        $data = ['first_name'=>'علی','last_name'=>'رضایی','mobile'=>'09129990001','employee_type'=>'concierge','services'=>['reception'],'permissions'=>['guests.view'],'is_active'=>1];
        $this->post(route('management.building-staff.store',$building),$data)->assertSessionHasNoErrors();
        $this->post(route('management.building-staff.store',$building),[...$data,'first_name'=>'مریم','last_name'=>'احمدی','mobile'=>'09129990002','employee_type'=>'guard','services'=>['security'],'permissions'=>[],'is_active'=>0])->assertSessionHasNoErrors();
        $pages['list'] = $this->get($url)->assertOk()->getContent();
        $pages['create'] = $this->get($url.'&create=1')->assertOk()->getContent();
        $staff = BuildingStaff::whereHas('user',fn($q)=>$q->where('mobile',$data['mobile']))->firstOrFail();
        $pages['edit'] = $this->get($url.'&edit='.$staff->id)->assertOk()->getContent();
        $this->from($url.'&edit='.$staff->id)->patch(route('management.building-staff.update',[$building,$staff]),[...$data,'services'=>[],'permissions'=>[],'is_active'=>0])->assertSessionHasErrors('services');
        $pages['error'] = $this->get($url.'&edit='.$staff->id)->assertOk()->getContent();
        return $pages;
    }
}

echo json_encode((new BuildingStaffFixture('renderPages'))->renderPages(),JSON_THROW_ON_ERROR);
