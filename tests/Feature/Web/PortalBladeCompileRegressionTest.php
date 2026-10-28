<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Database\Seeders\AccessScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalBladeCompileRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_uses_local_assets_even_when_cached_library_urls_and_integrity_are_stale(): void
    {
        $this->seed(AccessScenarioSeeder::class);
        foreach (['bootstrap' => ['css', 'js'], 'sweetalert2' => ['css', 'js'], 'jdate' => ['js']] as $library => $types) {
            foreach ($types as $type) {
                config(["management_ui.libraries.$library.$type" => "https://panel.buildino.ir/missing/$library.$type"]);
            }
        }
        config(['management_ui.libraries.bootstrap.js_integrity' => 'sha384-stale']);

        foreach (['/portal/login', '/management/login'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('/css/buildino-bootstrap.rtl.min.css?v=', false)
                ->assertDontSee('https://panel.buildino.ir/missing/', false);
        }
        $this->actingAs($this->user('role.owner@buildino.local'), 'web');
        $response = $this->get('/portal/resident')->assertOk()
            ->assertSee('/css/buildino-bootstrap.rtl.min.css?v=', false)
            ->assertSee('/css/buildino-sweetalert2.min.css?v=', false)
            ->assertSee('/js/buildino-bootstrap.bundle.min.js?v=', false)
            ->assertSee('/js/buildino-sweetalert2.all.min.js?v=', false)
            ->assertSee('/js/buildino-jdate.js?v=', false)
            ->assertDontSee('https://panel.buildino.ir/missing/', false)
            ->assertDontSee('sha384-stale', false);
        $response->assertSee('sha384-'.base64_encode(hash_file('sha384', public_path('js/buildino-bootstrap.bundle.min.js'), true)), false);
    }

    public function test_resident_portal_layout_compiles_and_renders(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $owner =
            $this->user(
                'role.owner@buildino.local'
            );

        $this->actingAs(
            $owner,
            'web'
        );

        $response = $this->get(
            '/portal/resident'
        )
            ->assertOk()
            ->assertSee(
                'پرتال BUILDINO'
            )
            ->assertSee(
                'خانه من'
            );
        $this->assertDashboardInsideLayout($response->getContent(), 'resident');
    }

    public function test_provider_portal_layout_compiles_and_renders(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $provider =
            $this->user(
                'role.provider@buildino.local'
            );

        $this->actingAs(
            $provider,
            'web'
        );

        $response = $this->get(
            '/portal/provider'
        )
            ->assertOk()
            ->assertSee(
                'پرتال BUILDINO'
            )
            ->assertSee(
                'پنل ارائه‌دهنده خدمات'
            );
        $this->assertDashboardInsideLayout($response->getContent(), 'provider');
    }

    private function assertDashboardInsideLayout(string $html, string $area): void
    {
        $this->assertStringStartsWith('<!doctype html>', ltrim($html));
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument;
            $document->loadHTML($html, LIBXML_NONET);
            $xpath = new \DOMXPath($document);
            $this->assertSame(1, $xpath->query(
                '//main[@id="mainContent"]/div[contains(@class,"portal-content")]/section[contains(@class,"portal-hero--'.$area.'")]'
            )->length);
            $this->assertSame(1, $xpath->query(
                '//main[@id="mainContent"]/div[contains(@class,"portal-content")]/section[1][@data-pending-invitation]'
            )->length);
            $this->assertSame(1, $xpath->query('//section[@data-pending-invitation]//form[@data-invitation-code-form]//input[@data-invitation-code]')->length);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function user(
        string $email
    ): User {
        return User::query()
            ->where(
                'email',
                $email
            )
            ->firstOrFail();
    }
}
