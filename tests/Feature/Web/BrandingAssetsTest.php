<?php

namespace Tests\Feature\Web;

use Tests\TestCase;

class BrandingAssetsTest extends TestCase
{
    public function test_transparent_brand_assets_are_available(): void
    {
        foreach ([
            'images/buildino-logo.svg',
            'images/buildino-mark.svg',
            'images/buildino-logo-horizontal.svg',
            'images/buildino-logo-horizontal-light.svg',
        ] as $path) {
            $file = public_path($path);
            $this->assertFileExists($file);
            $contents = file_get_contents($file);
            $this->assertIsString($contents);
            $this->assertStringContainsString('<svg', $contents);
            $this->assertStringNotContainsString('<rect', $contents);
        }
    }

    public function test_public_home_renders_brand_and_favicon(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('images/buildino-logo-horizontal-light.svg', false)
            ->assertSee('images/buildino-mark.svg', false);
    }

    public function test_portal_login_renders_the_shared_brand(): void
    {
        $this->get('/portal/login')
            ->assertOk()
            ->assertSee('images/buildino-logo.svg', false)
            ->assertSee('images/buildino-mark.svg', false);
    }
}
