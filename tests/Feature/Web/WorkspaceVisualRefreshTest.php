<?php

namespace Tests\Feature\Web;

use App\Models\UnitOwnership;
use App\Models\User;
use Database\Seeders\AccessScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceVisualRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessScenarioSeeder::class);
    }

    public function test_monthly_statements_expose_unit_context_and_receipt_dialog_inside_glance(): void
    {
        $unit = UnitOwnership::firstOrFail()->unit;
        $this->actingAs(User::where('email', 'role.building@buildino.local')->firstOrFail())
            ->get(route('management.unit-statements', ['unit' => $unit, 'glance_embed' => 1]))
            ->assertOk()->assertSee('data-glance-embedded', false)
            ->assertSee('aria-controls="statement-deposit-dialog"', false)
            ->assertSee('id="statement-deposit-dialog"', false)
            ->assertSee('name="receipt"', false)
            ->assertDontSee('بازگشت به داشبورد');
    }

    public function test_resident_statement_navigation_identifies_invoices_as_current_page(): void
    {
        $this->actingAs(User::where('email', 'role.owner@buildino.local')->firstOrFail());
        $html = $this->get('/portal/resident/operations/invoices')->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//nav[contains(@class,"portal-nav")]/a[contains(@href,"/operations/invoices")][@aria-current="page"]')->length);
    }

    public function test_provider_lists_have_only_relevant_status_options_and_consistent_navigation(): void
    {
        $this->actingAs(User::where('email', 'role.provider@buildino.local')->firstOrFail());
        $this->get('/portal/provider/operations/services')->assertOk()
            ->assertSee('کارهای من')->assertDontSee('سررسید گذشته')->assertDontSee('پرداخت ناقص');
        $this->get('/portal/provider')->assertOk()->assertSee('/portal/provider/operations/services', false)
            ->assertSee('/portal/provider/operations/payouts', false);
    }

    public function test_charge_sms_uses_finance_navigation_and_explains_calculated_amount(): void
    {
        $this->actingAs(User::where('email', 'role.building@buildino.local')->firstOrFail())
            ->get('/management/operations/charge-sms')->assertOk()
            ->assertSee('data-finance-navigation', false)->assertSee('مبلغ محاسبه‌شده دوره');
    }

    public function test_provider_quote_currency_follows_the_assigned_building(): void
    {
        $provider = User::where('email', 'role.provider@buildino.local')->firstOrFail();
        $job = \App\Models\ServiceRequest::where('assigned_to', $provider->id)->firstOrFail();
        $job->building->update(['currency' => 'IRT']);
        $this->actingAs($provider)->get('/portal/provider')->assertOk()->assertSee('data-service-currency="IRT"', false);
    }
}
