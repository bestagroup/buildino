<?php

namespace Tests\Feature\System;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class MaterializeReferenceUiIntegrationTest extends TestCase
{
    public function test_management_and_portal_follow_reference_materialize_layout(): void
    {
        $management = file_get_contents(resource_path('views/management/layouts/app.blade.php'));
        $portal = file_get_contents(resource_path('views/portal/layouts/app.blade.php'));

        foreach ([$management, $portal] as $layout) {
            foreach ([
                'data-template="vertical-menu-template"',
                'layout-wrapper',
                'layout-content-navbar',
                'layout-container',
                'layout-page',
                'layout-navbar',
                'navbar-detached',
                'bg-navbar-theme',
                'content-wrapper',
                'container-xxl',
                'css/buildino-materialize.css',
                'js/buildino-materialize.js',
            ] as $marker) {
                $this->assertStringContainsString($marker, $layout);
            }

            // Materialize core already contains Bootstrap styling. Loading a second
            // Bootstrap stylesheet was a major source of the previous shell drift.
            $this->assertStringNotContainsString(
                "management_ui.libraries.bootstrap.css",
                $layout
            );
        }
    }

    public function test_sidebars_follow_reference_vertical_menu_structure(): void
    {
        foreach ([
            resource_path('views/management/layouts/partials/sidebar.blade.php'),
            resource_path('views/portal/layouts/partials/sidebar.blade.php'),
        ] as $path) {
            $sidebar = file_get_contents($path);

            foreach ([
                'layout-menu menu-vertical menu bg-menu-theme',
                'app-brand',
                'app-brand-link',
                'menu-inner-shadow',
                'menu-inner py-1',
                'menu-header',
                'menu-item',
                'menu-link',
                'menu-icon',
                'data-materialize-menu-collapse',
            ] as $marker) {
                $this->assertStringContainsString($marker, $sidebar, $path);
            }
        }
    }

    public function test_legacy_theme_layers_are_not_referenced_by_any_blade_view(): void
    {
        $legacy = [
            'buildino-template.css',
            'buildino-template.js',
            'buildino-materio-phase2.css',
            'buildino-materio-phase3.css',
            'buildino-materio-shell-recovery.css',
            'buildino-materio-shell-recovery.js',
        ];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                resource_path('views'),
                \FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            foreach ($legacy as $asset) {
                $this->assertStringNotContainsString(
                    $asset,
                    $contents,
                    $file->getPathname().' still references '.$asset
                );
            }
        }
    }

    public function test_materialize_adapter_does_not_bundle_font_binaries(): void
    {
        $extensions = ['ttf', 'otf', 'woff', 'woff2', 'eot'];
        $fontFiles = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                public_path(),
                \FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && in_array(strtolower($file->getExtension()), $extensions, true)) {
                $fontFiles[] = $file->getPathname();
            }
        }

        $this->assertSame([], $fontFiles);
    }
}
