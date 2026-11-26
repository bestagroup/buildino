<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Database\Seeders\AccessScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicesPreviewNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_preview_is_visible_to_building_manager_without_links(): void
    {
        $this->seed(AccessScenarioSeeder::class);
        $manager = User::where('email', 'role.building@buildino.local')->firstOrFail();
        $response = $this->actingAs($manager)->get('/management')->assertOk();
        preg_match('/<section[^>]*data-nav-group="services-preview".*?<\/section>/s', $response->getContent(), $section);
        $this->assertNotEmpty($section);
        $this->assertSame(10, substr_count($section[0], 'aria-disabled="true"'));
        $this->assertStringNotContainsString('href=', $section[0]);
        $this->assertStringContainsString('خدمات مالی و اعتباری', $section[0]);
        $this->assertStringContainsString('باشگاه مشتریان', $section[0]);
        $operator = User::where('email', 'role.operator@buildino.local')->firstOrFail();
        $this->actingAs($operator)->get('/management')->assertOk()->assertDontSee('data-nav-group="services-preview"', false);
    }
}
