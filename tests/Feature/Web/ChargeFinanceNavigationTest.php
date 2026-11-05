<?php

namespace Tests\Feature\Web;

use App\Support\ManagementPageCatalog;
use Tests\TestCase;

final class ChargeFinanceNavigationTest extends TestCase
{
    public function test_blade_charge_pages_show_ordered_authorized_tabs_with_building_context(): void
    {
        request()->query->set('building_id', 15);
        foreach (['charge-formulas', 'charge-periods'] as $resource) {
            $html = view('management.partials.finance-navigation', [
                'currentResource' => $resource,
                'managementUi' => ['visible_resources' => ManagementPageCatalog::CHARGE_TABS],
            ])->render();
            preg_match('/<div class="finance-tabs">(.*?)<\/div>/s', $html, $match);
            $tabs = $match[1];
            $previous = -1;
            foreach (ManagementPageCatalog::CHARGE_TABS as $key) {
                $position = strpos($tabs, '/operations/'.$key.'?building_id=15');
                $this->assertNotFalse($position);
                $this->assertGreaterThan($previous, $position);
                $previous = $position;
            }
            $this->assertStringContainsString('aria-current="page"', $tabs);
        }
        $html = view('management.partials.finance-navigation', [
            'currentResource' => 'charge-periods',
            'managementUi' => ['visible_resources' => ['charge-periods']],
        ])->render();
        $this->assertStringNotContainsString('/operations/expenses?', $html);
        $this->assertStringNotContainsString('/operations/invoices?', $html);
    }
}
