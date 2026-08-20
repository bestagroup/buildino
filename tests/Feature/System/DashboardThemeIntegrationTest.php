<?php

namespace Tests\Feature\System;

use Tests\TestCase;

class DashboardThemeIntegrationTest extends TestCase
{
    public function test_materialize_theme_assets_are_available_without_replacing_operational_assets(): void
    {
        $required = [
            public_path('assets/vendor/css/rtl/core.css'),
            public_path('assets/vendor/css/rtl/theme-default.css'),
            public_path('assets/vendor/css/rtl/core-dark.css'),
            public_path('assets/vendor/css/rtl/theme-default-dark.css'),
            public_path('assets/css/materialize-demo.css'),
            public_path('css/buildino-materialize.css'),
            public_path('js/buildino-materialize.js'),
        ];

        foreach ($required as $path) {
            $this->assertFileExists($path, $path);
            $this->assertGreaterThan(0, filesize($path));
        }

        $management = file_get_contents(
            resource_path('views/management/layouts/app.blade.php')
        );
        $portal = file_get_contents(
            resource_path('views/portal/layouts/app.blade.php')
        );

        foreach ([$management, $portal] as $layout) {
            $this->assertStringContainsString(
                'assets/vendor/css/rtl/core.css',
                $layout
            );
            $this->assertStringContainsString(
                'assets/vendor/css/rtl/theme-default.css',
                $layout
            );
            $this->assertStringContainsString(
                'assets/css/materialize-demo.css',
                $layout
            );
            $this->assertStringContainsString(
                'css/buildino-materialize.css',
                $layout
            );
            $this->assertStringContainsString(
                'js/buildino-materialize.js',
                $layout
            );

            // Legacy stacked theme adapters must no longer own the shell.
            $this->assertStringNotContainsString('css/buildino-template.css', $layout);
            $this->assertStringNotContainsString('css/buildino-materio-phase2.css', $layout);
            $this->assertStringNotContainsString('css/buildino-materio-phase3.css', $layout);
            $this->assertStringNotContainsString('css/buildino-materio-shell-recovery.css', $layout);
        }

        // Operational JavaScript remains untouched by the visual migration.
        $this->assertStringContainsString('js/buildino-management.js', $management);
        $this->assertStringContainsString('js/buildino-portal.js', $portal);
        $this->assertStringContainsString('js/buildino-datatables.js', $management);
        $this->assertStringContainsString('js/buildino-datatables.js', $portal);
    }

    public function test_theme_bundle_contains_no_font_binary_files(): void
    {
        $extensions = ['ttf', 'otf', 'woff', 'woff2', 'eot'];
        $fontFiles = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                public_path(),
                \FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            if (in_array(strtolower($file->getExtension()), $extensions, true)) {
                $fontFiles[] = $file->getPathname();
            }
        }

        $this->assertSame([], $fontFiles);
    }
}
