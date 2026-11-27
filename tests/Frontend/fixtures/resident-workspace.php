<?php

use App\Models\BuildingFacility;
use App\Models\UnitInvoice;
use App\Models\User;
use App\Services\Web\PortalAccessService;
use Database\Seeders\AccessScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

// Render the real routes against an isolated database; never touch local data.
putenv('APP_ENV=testing');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
putenv('SESSION_DRIVER=array');
putenv('CACHE_STORE=array');
require __DIR__.'/../../../vendor/autoload.php';

final class ResidentWorkspaceFixture extends TestCase
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
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Fixture requires SQLite memory.');
        }
        $this->seed(AccessScenarioSeeder::class);
        config(['payment_gateways.default' => 'generic', 'payment_gateways.gateways.generic.enabled' => true]);
        URL::forceRootUrl('http://localhost');
        if (($GLOBALS['argv'][1] ?? '') === 'finance') {
            return $this->financePages();
        }
        $this->actingAs(User::where('email', 'role.owner@buildino.local')->firstOrFail(), 'web');
        $unit = app(PortalAccessService::class)->residentUnits(auth('web')->user())->firstOrFail();
        $facility = BuildingFacility::create([
            'building_id' => $unit->floor->block->building_id, 'title' => 'سالن اجتماعات', 'code' => 'BROWSER-HALL',
            'type' => 'meeting_hall', 'default_price' => 10000, 'requires_payment' => true, 'is_active' => true,
        ]);
        $facility->facilityReservationRules()->create(['cancel_before_minutes' => 60, 'refund_percentage' => 50]);
        $invoice = UnitInvoice::where('outstanding_amount', '>', 0)->firstOrFail();
        $ticket = \App\Models\SupportTicket::create([
            'user_id' => auth('web')->id(), 'building_id' => $unit->floor->block->building_id, 'unit_id' => $unit->id,
            'ticket_number' => 'BROWSER-TICKET', 'subject' => 'پشتیبانی آزمایشی', 'description' => 'گفتگو', 'priority' => 'medium', 'status' => 'open',
        ]);
        $pages = [];
        foreach (['home' => '/portal/resident', 'invoices' => '/portal/resident/operations/invoices',
            'guests' => '/portal/resident/operations/guests', 'reservations' => '/portal/resident/operations/reservations',
            'units' => '/portal/resident/operations/units', 'wallet' => '/portal/resident/operations/wallet',
            'services' => '/portal/resident/operations/services', 'loyalty' => '/portal/resident/operations/loyalty',
            'invoice' => '/portal/resident/operations/invoices/'.$invoice->id,
            'support' => '/portal/resident/operations/support/'.$ticket->id] as $key => $url) {
            $response = $this->get($url)->assertOk();
            $pages[$key] = $response->getContent();
        }
        $pages['rows'] = $this->getJson('/portal/resident/datatables/invoices?draw=1&start=0&length=15')->assertOk()->json();
        foreach (['invoices', 'guests', 'reservations', 'services', 'support', 'wallet'] as $resource) {
            $pages['tables'][$resource] = $this->getJson('/portal/resident/datatables/'.$resource.'?draw=1&start=0&length=15')->assertOk()->json();
        }

        return $pages;
    }

    private function financePages(): array
    {
        $owner = User::where('email', 'role.owner@buildino.local')->firstOrFail();
        $manager = User::where('email', 'role.building@buildino.local')->firstOrFail();
        $provider = User::where('email', 'role.provider@buildino.local')->firstOrFail();
        $this->actingAs($owner);
        $unit = app(PortalAccessService::class)->residentUnits($owner)->firstOrFail();
        $building = $unit->floor->block->building;
        $period = \App\Models\ChargePeriod::create(['building_id'=>$building->id,'title'=>'شارژ آبان ۱۴۰۵','period_start'=>'2026-10-23','period_end'=>'2026-11-21','due_date'=>'2026-11-25','status'=>'issued','created_by'=>$manager->id]);
        foreach ([$owner, User::factory()->create(['first_name'=>'مریم','last_name'=>'احمدی','mobile'=>'09121112244','is_active'=>true,'is_blocked'=>false])] as $index=>$resident) {
            $target = $index === 0 ? $unit : $unit->replicate();
            if ($index) { $target->unit_number = '1022'; $target->save(); }
            \App\Models\UnitOccupancy::where('unit_id',$target->id)->delete();
            \App\Models\UnitOccupancy::create(['unit_id'=>$target->id,'user_id'=>$resident->id,'occupancy_type'=>'owner','starts_at'=>$period->period_start,'ends_at'=>$period->period_end,'is_primary'=>false,'is_active'=>false]);
            $invoice = UnitInvoice::create(['building_id'=>$building->id,'unit_id'=>$target->id,'charge_period_id'=>$period->id,'invoice_number'=>'BROWSER-MONTH-'.$index,'payer_responsibility'=>'unit','issue_date'=>'2026-10-23','due_date'=>'2026-11-25','period_start'=>$period->period_start,'period_end'=>$period->period_end,'subtotal'=>100000,'total_amount'=>100000,'outstanding_amount'=>100000,'paid_amount'=>0,'status'=>'issued']);
            $invoice->invoiceItems()->create(['title'=>'هزینه نگهداری و نظافت مشاعات ساختمان','quantity'=>2,'unit_amount'=>50000,'total_amount'=>100000]);
        }
        $pages['statements'] = $this->get('/portal/resident/operations/invoices')->assertOk()->getContent();
        $this->actingAs($manager);
        $pages['manager'] = $this->get(route('management.unit-statements',['unit'=>$unit,'glance_embed'=>1]))->assertOk()->getContent();
        config(['notifications.sms_provider'=>'ghasedak','notifications.sms_templates.charge.notice.template'=>'buildinodebit']);
        $pages['sms'] = $this->get('/management/operations/charge-sms?building_id='.$building->id.'&period_id='.$period->id)->assertOk()->getContent();
        $this->actingAs($provider);
        $job = \App\Models\ServiceRequest::where('assigned_to',$provider->id)->firstOrFail();
        $job->building->update(['currency'=>'IRT']);
        $wallet = app(\App\Services\Wallet\WalletService::class)->walletFor($provider);
        app(\App\Services\Wallet\WalletService::class)->credit($wallet,1000000,\App\Enums\WalletTransferType::TopUp,'browser-provider-funds');
        \App\Models\ProviderBankAccount::create(['user_id'=>$provider->id,'bank_name'=>'بانک آزمایشی','account_holder_name'=>'کاربر خدمات','iban'=>'IR820540102680020817909002','is_verified'=>true,'is_default'=>true]);
        $pages['provider'] = $this->get('/portal/provider')->assertOk()->getContent();
        foreach (['services','wallet','payouts'] as $resource) {
            $pages['provider-'.$resource] = $this->get('/portal/provider/operations/'.$resource)->assertOk()->getContent();
            $pages['tables'][$resource] = $this->getJson('/portal/provider/datatables/'.$resource.'?draw=1&start=0&length=15')->assertOk()->json();
        }
        $pages['provider-detail'] = $this->get('/portal/provider/operations/services/'.$job->id)->assertOk()->getContent();
        return $pages;
    }
}

echo json_encode((new ResidentWorkspaceFixture('renderPages'))->renderPages(), JSON_THROW_ON_ERROR);
