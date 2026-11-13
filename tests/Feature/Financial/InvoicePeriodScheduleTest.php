<?php

namespace Tests\Feature\Financial;

use App\Models\ChargePeriod;
use App\Models\UnitInvoice;
use App\Services\ChargePeriodService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

final class InvoicePeriodScheduleTest extends TestCase
{
    use RefreshDatabase, CreatesBuildingDomainData;

    private function periods(int $buildingId, int $count = 5): array
    {
        return array_map(fn ($i) => ChargePeriod::create([
            'building_id' => $buildingId, 'title' => 'دوره '.$i,
            'period_start' => now()->startOfMonth()->addMonths($i)->toDateString(),
            'period_end' => now()->startOfMonth()->addMonths($i)->endOfMonth()->toDateString(),
            'due_date' => now()->startOfMonth()->addMonths($i + 1)->addDays(5)->toDateString(), 'status' => 'draft',
        ]), range(0, $count - 1));
    }

    private function data(ChargePeriod $period, int $count = 1, int $amount = 1001): array
    {
        return ['start_charge_period_id' => $period->id, 'period_installment_count' => $count,
            'items' => [['title' => 'بدهی واحد', 'quantity' => 1, 'unit_amount' => $amount]]];
    }

    public function test_five_period_debt_is_exact_and_only_current_installment_is_issued_once(): void
    {
        $graph = $this->createBuildingGraph(); $actor = $this->createUser();
        $periods = $this->periods($graph['building']->id);
        $invoices = app(InvoiceService::class); $charges = app(ChargePeriodService::class);
        $source = $invoices->createManual($graph['unit'], $actor, $this->data($periods[0], 5));
        $this->assertSame([200, 200, 200, 200, 201], $source->periodAllocations()->orderBy('sequence')->pluck('amount')->all());
        $this->assertSame('draft', $source->status->value);
        foreach ($periods as $index => $period) {
            $preview = $charges->preview($period);
            $this->assertSame([], $preview['errors']);
            $this->assertSame($index === 4 ? 201 : 200, $preview['scheduled_debt_total']);
            $charges->calculate($period, $preview['review_token']);
            $charges->calculate($period, $charges->preview($period)['review_token']);
            $charges->issue($period, $actor);
            $this->assertSame(1, $period->unitInvoices()->count());
            $this->assertSame($index === 4 ? 201 : 200, $period->unitInvoices()->first()->total_amount);
        }
        $this->assertSame(5, $source->periodAllocations()->whereNotNull('issued_invoice_id')->count());
        $this->assertSame(1001, (int) UnitInvoice::whereNotNull('charge_period_id')->sum('total_amount'));
        foreach (['edit', 'delete', 'issue'] as $action) {
            try {
                match ($action) {
                    'edit' => $invoices->updateDraft($source, $this->data($periods[0], 5, 2000)),
                    'delete' => $invoices->voidDraft($source),
                    'issue' => $invoices->issue($source),
                };
                $this->fail('Issued schedule was mutable: '.$action);
            } catch (ValidationException) { $this->assertTrue(true); }
        }
        try { $charges->issue($periods[0], $actor); $this->fail('Period issued twice'); }
        catch (ValidationException) { $this->assertSame(5, UnitInvoice::whereNotNull('charge_period_id')->count()); }
    }

    public function test_edit_invalidates_preview_and_calculation_and_one_installment_is_all_in_selected_period(): void
    {
        $graph = $this->createBuildingGraph(); $actor = $this->createUser();
        [$period] = $this->periods($graph['building']->id, 1);
        $invoices = app(InvoiceService::class); $charges = app(ChargePeriodService::class);
        $source = $invoices->createManual($graph['unit'], $actor, $this->data($period));
        $preview = $charges->preview($period);
        $charges->calculate($period, $preview['review_token']);
        $invoices->updateDraft($source, $this->data($period, 1, 2001));
        try { $charges->calculate($period, $preview['review_token']); $this->fail('Stale preview accepted'); }
        catch (ValidationException) { $this->assertTrue(true); }
        try { $charges->issue($period, $actor); $this->fail('Stale calculation accepted'); }
        catch (ValidationException) { $this->assertTrue(true); }
        $charges->calculate($period, $charges->preview($period)['review_token']);
        $charges->issue($period, $actor);
        $this->assertSame(2001, $period->unitInvoices()->first()->total_amount);
    }

    public function test_foreign_closed_missing_or_excess_periods_are_rejected_without_partial_writes(): void
    {
        $graph = $this->createBuildingGraph(); $other = $this->createBuildingGraph(); $actor = $this->createUser();
        $periods = $this->periods($graph['building']->id, 2);
        [$foreign] = $this->periods($other['building']->id, 1);
        $periods[1]->update(['status' => 'issued']);
        foreach ([$this->data($foreign), $this->data($periods[0], 2), $this->data($periods[0], 3), $this->data($periods[0], 6)] as $data) {
            try { app(InvoiceService::class)->createManual($graph['unit'], $actor, $data); $this->fail('Invalid schedule accepted'); }
            catch (ValidationException) { $this->assertDatabaseCount('unit_invoices', 0); $this->assertDatabaseCount('invoice_period_allocations', 0); }
        }
    }

    public function test_api_accepts_schedule_without_dates_and_enforces_building_scope(): void
    {
        $graph = $this->createBuildingGraph(); $other = $this->createBuildingGraph(); $actor = $this->createUser();
        [$period] = $this->periods($graph['building']->id, 1);
        [$foreign] = $this->periods($other['building']->id, 1);
        $role = \App\Models\Role::create(['name' => 'invoice-period-manager', 'display_name' => 'Manager']);
        foreach (['invoices.create', 'invoices.view', 'invoices.update', 'reports.dashboard.view'] as $name) {
            $role->permissions()->attach(\App\Models\Permission::firstOrCreate(['name' => $name], ['display_name' => $name, 'module' => explode('.', $name)[0]]));
        }
        \App\Models\UserRoleAssignment::create(['user_id' => $actor->id, 'role_id' => $role->id,
            'scope_type' => $graph['building']->getMorphClass(), 'scope_id' => $graph['building']->id, 'is_active' => true]);
        \Laravel\Sanctum\Sanctum::actingAs($actor);
        $url = '/api/v1/units/'.$graph['unit']->id.'/invoices';
        $this->postJson($url, $this->data($foreign))->assertUnprocessable();
        $this->postJson('/api/v1/units/'.$other['unit']->id.'/invoices', $this->data($foreign))->assertForbidden();
        $this->postJson($url, $this->data($period, 6))->assertUnprocessable();
        $created = $this->postJson($url, $this->data($period))->assertCreated()
            ->assertJsonPath('data.start_charge_period_id', $period->id)
            ->assertJsonPath('data.period_allocations.0.amount', 1001);
        $this->patchJson('/api/v1/invoices/'.$created->json('data.id'), $this->data($period, 1, 3001))
            ->assertOk()->assertJsonPath('data.period_allocations.0.amount', 3001);
        $this->get('/management/operations/invoices')->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->has('workspace.periods', 1)->where('workspace.periods.0.id', $period->id));
    }

    public function test_period_charge_and_scheduled_debt_are_added_and_reported_together(): void
    {
        $graph = $this->createBuildingGraph(); $actor = $this->createUser();
        [$period] = $this->periods($graph['building']->id, 1);
        $category = $graph['building']->financialCategories()->where('default_key', 'general.1')->firstOrFail();
        $formula = \App\Models\ChargeFormula::create(['building_id' => $graph['building']->id, 'title' => 'ثابت', 'calculation_type' => 'fixed', 'is_active' => true]);
        $formula->chargeItems()->create(['financial_category_id' => $category->id, 'title' => 'شارژ ثابت', 'base_amount' => 500]);
        app(InvoiceService::class)->createManual($graph['unit'], $actor, $this->data($period));
        $charges = app(ChargePeriodService::class);
        $charges->calculate($period, $charges->preview($period)['review_token']);
        $report = app(\App\Services\Web\BuildingAtGlanceService::class)->report($graph['building']);
        $this->assertSame(1501, $report['rows']->first()['latest']);
        $charges->issue($period, $actor);
        $this->assertSame(1501, $period->unitInvoices()->first()->total_amount);
        $this->assertSame(2, $period->unitInvoices()->first()->invoiceItems()->count());
    }

    public function test_signed_credit_and_debt_can_cancel_to_zero_without_double_counting(): void
    {
        $graph = $this->createBuildingGraph(); $actor = $this->createUser();
        [$period] = $this->periods($graph['building']->id, 1);
        foreach ([1001, -1001] as $amount) app(InvoiceService::class)->createManual($graph['unit'], $actor, $this->data($period, 1, $amount));
        $charges = app(ChargePeriodService::class);
        $charges->calculate($period, $charges->preview($period)['review_token']);
        $charges->issue($period, $actor);
        $this->assertSame(0, $period->unitInvoices()->first()->total_amount);
        $this->assertSame('paid', $period->unitInvoices()->first()->status->value);
        $this->assertDatabaseCount('invoice_period_allocations', 2);
    }
}
