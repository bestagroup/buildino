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

        $this->get(
            '/portal/resident'
        )
            ->assertOk()
            ->assertSee(
                'BUILDINO PORTAL'
            )
            ->assertSee(
                'خانه من'
            );
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

        $this->get(
            '/portal/provider'
        )
            ->assertOk()
            ->assertSee(
                'BUILDINO PORTAL'
            )
            ->assertSee(
                'پنل ارائه‌دهنده خدمات'
            );
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
