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
            'invoice' => '/portal/resident/operations/invoices/'.$invoice->id,
            'support' => '/portal/resident/operations/support/'.$ticket->id] as $key => $url) {
            $response = $this->get($url)->assertOk();
            $pages[$key] = $response->getContent();
        }
        $pages['rows'] = $this->getJson('/portal/resident/datatables/invoices?draw=1&start=0&length=15')->assertOk()->json();

        return $pages;
    }
}

echo json_encode((new ResidentWorkspaceFixture('renderPages'))->renderPages(), JSON_THROW_ON_ERROR);
