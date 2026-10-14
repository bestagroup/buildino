<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Database\Seeders\AccessScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalBladeCompileRegressionTest extends TestCase
{
    use RefreshDatabase;

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
