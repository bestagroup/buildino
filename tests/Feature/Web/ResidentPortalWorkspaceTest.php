<?php

namespace Tests\Feature\Web;

use App\Models\BuildingFacility;
use App\Models\Unit;
use App\Models\UnitInvitation;
use App\Models\UnitInvoice;
use App\Models\UnitOwnership;
use App\Models\User;
use App\Services\Web\PortalAccessService;
use Database\Seeders\AccessScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentPortalWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessScenarioSeeder::class);
        $this->actingAs(User::where('email', 'role.owner@buildino.local')->firstOrFail(), 'web');
    }

    public function test_home_is_a_compact_entry_point_with_links_to_workspaces(): void
    {
        $this->get('/portal/resident')->assertOk()
            ->assertSee('data-resident-dashboard', false)
            ->assertSee('/portal/resident/operations/guests', false)
            ->assertSee('/portal/resident/operations/loyalty', false)
            ->assertDontSee('id="guestModal"', false)
            ->assertDontSee('id="resident-dashboard-invoices"', false);
    }

    public function test_each_work_page_has_consistent_navigation_and_only_its_own_creation_form(): void
    {
        foreach (['guests' => 'guest', 'reservations' => 'reservation', 'services' => 'service', 'support' => 'support'] as $resource => $form) {
            $this->get('/portal/resident/operations/'.$resource)->assertOk()
                ->assertSee('data-resident-workspace', false)
                ->assertSee('data-resident-create', false)
                ->assertSee('data-portal-form="'.$form.'"', false)
                ->assertDontSee('<option value="issued">', false);
        }
    }

    public function test_payable_invoice_detail_exposes_payment_only_when_gateway_is_enabled(): void
    {
        $invoice = UnitInvoice::where('invoice_number', 'like', '%')->where('outstanding_amount', '>', 0)->firstOrFail();
        config(['payment_gateways.default' => 'generic', 'payment_gateways.gateways.generic.enabled' => true]);
        $this->get('/portal/resident/operations/invoices/'.$invoice->id)->assertOk()
            ->assertSee('data-pay-invoice', false)->assertSee('data-outstanding="'.$invoice->outstanding_amount.'"', false);
        config(['payment_gateways.gateways.generic.enabled' => false]);
        $this->get('/portal/resident/operations/invoices/'.$invoice->id)->assertOk()
            ->assertDontSee('data-pay-invoice', false)->assertSee('پرداخت آنلاین در حال حاضر در دسترس نیست');
    }

    public function test_unit_filter_cannot_expand_resident_scope(): void
    {
        $this->getJson('/portal/resident/datatables/invoices?unit_id=999999')->assertForbidden();
        $this->getJson('/portal/resident/datatables/invoices?unit_id=bad')->assertUnprocessable();
    }

    public function test_multi_unit_resident_can_filter_without_seeing_other_units_records(): void
    {
        $owner = auth('web')->user();
        $ownership = UnitOwnership::where('user_id', $owner->id)->firstOrFail();
        $otherUnit = Unit::where('id', '!=', $ownership->unit_id)->firstOrFail();
        $secondOwnership = $ownership->replicate();
        $secondOwnership->unit_id = $otherUnit->id;
        $secondOwnership->save();
        $firstInvoice = UnitInvoice::where('unit_id', $ownership->unit_id)->firstOrFail();
        $secondInvoice = $firstInvoice->replicate();
        $secondInvoice->unit_id = $otherUnit->id;
        $secondInvoice->invoice_number = 'WORKSPACE-SECOND-UNIT';
        $secondInvoice->save();
        $rows = $this->getJson('/portal/resident/datatables/invoices?unit_id='.$ownership->unit_id)->assertOk()->json('data');
        $this->assertNotEmpty($rows);
        $this->assertSame([(int) $ownership->unit_id], collect($rows)->pluck('unit_id')->unique()->all());
        $this->get('/portal/resident/operations/invoices?unit_id='.$otherUnit->id)->assertOk()->assertSee($otherUnit->title);
    }

    public function test_invited_resident_without_a_unit_gets_an_invitation_empty_state(): void
    {
        $user = User::factory()->create(['is_active' => true, 'is_blocked' => false]);
        $unit = Unit::firstOrFail();
        UnitInvitation::create([
            'unit_id' => $unit->id, 'invited_by' => auth('web')->id(), 'invited_user_id' => $user->id,
            'mobile' => $user->mobile, 'relation_type' => 'tenant', 'channel' => 'sms', 'token' => 'workspace-invite', 'status' => 'sent',
        ]);
        $this->actingAs($user, 'web');
        $this->get('/portal/resident')->assertOk()->assertSee('data-invitation-open', false);
        $this->get('/portal/resident/operations/guests')->assertOk()
            ->assertSee('واحدی متصل نشده است')->assertDontSee('js-server-datatable', false);
        $this->getJson('/portal/resident/datatables/invoices')->assertOk()->assertJsonPath('recordsTotal', 0);
    }

    public function test_paid_invoice_does_not_offer_another_payment(): void
    {
        $invoice = UnitInvoice::where('outstanding_amount', '>', 0)->firstOrFail();
        $invoice->update(['status' => 'paid', 'outstanding_amount' => 0]);
        config(['payment_gateways.default' => 'generic', 'payment_gateways.gateways.generic.enabled' => true]);
        $this->get('/portal/resident/operations/invoices/'.$invoice->id)->assertOk()->assertDontSee('data-pay-invoice', false);
    }

    public function test_reservation_options_expose_real_currency_and_rules(): void
    {
        $unit = app(PortalAccessService::class)->residentUnits(auth('web')->user())->firstOrFail();
        $facility = BuildingFacility::create([
            'building_id' => $unit->floor->block->building_id, 'title' => 'سالن تست', 'code' => 'WORKSPACE-HALL',
            'type' => 'meeting_hall', 'default_price' => 10000, 'requires_payment' => true, 'is_active' => true,
        ]);
        $facility->facilityReservationRules()->create(['cancel_before_minutes' => 60, 'refund_percentage' => 50]);
        $this->get('/portal/resident/operations/reservations')->assertOk()
            ->assertSee('data-currency=', false)->assertSee('data-policy=', false)
            ->assertSee('۶۰ دقیقه')->assertSee('۵۰٪');
    }

    public function test_unit_and_loyalty_pages_are_real_pages_and_do_not_render_datatables(): void
    {
        foreach (['units', 'loyalty'] as $resource) {
            $this->get('/portal/resident/operations/'.$resource)->assertOk()
                ->assertSee('data-resident-workspace', false)->assertDontSee('js-server-datatable', false);
        }
    }
}
