<?php

namespace Tests\Feature\System;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class MaterioUiPhase2IntegrationTest extends TestCase
{
    public function test_operational_assets_and_final_materialize_adapter_are_present(): void
    {
        $required = [
            'assets/vendor/css/rtl/core.css',
            'assets/vendor/css/rtl/theme-default.css',
            'assets/vendor/css/rtl/core-dark.css',
            'assets/vendor/css/rtl/theme-default-dark.css',
            'assets/css/materialize-demo.css',
            'css/buildino-foundation.css',
            'css/buildino-management.css',
            'css/buildino-portal.css',
            'css/buildino-crud.css',
            'css/buildino-datatables.css',
            'css/buildino-materialize.css',
            'js/buildino-foundation.js',
            'js/buildino-management.js',
            'js/buildino-portal.js',
            'js/buildino-crud.js',
            'js/buildino-datatables.js',
            'js/buildino-materialize.js',
        ];

        foreach ($required as $relativePath) {
            $path = public_path($relativePath);

            $this->assertFileExists($path, $relativePath);
            $this->assertGreaterThan(0, filesize($path), $relativePath);
        }
    }

    public function test_every_literal_local_asset_reference_in_blade_exists(): void
    {
        $missing = [];
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

            preg_match_all(
                "/asset\\(\\s*['\"]([^'\"]+)['\"]\\s*\\)/",
                $contents,
                $matches
            );

            foreach ($matches[1] ?? [] as $relativePath) {
                if (! file_exists(public_path($relativePath))) {
                    $missing[] = $file->getFilename().' -> '.$relativePath;
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)));
    }

    public function test_final_component_adapter_loads_after_page_specific_styles(): void
    {
        foreach ([
            resource_path('views/management/layouts/app.blade.php'),
            resource_path('views/portal/layouts/app.blade.php'),
        ] as $layoutPath) {
            $layout = file_get_contents($layoutPath);

            $stackPosition = strpos($layout, "@stack('styles')");
            $materializePosition = strpos($layout, 'css/buildino-materialize.css');

            $this->assertNotFalse($stackPosition, $layoutPath);
            $this->assertNotFalse($materializePosition, $layoutPath);
            $this->assertGreaterThan(
                $stackPosition,
                $materializePosition,
                'The Materialize presentation adapter must load after page-specific styles.'
            );
        }
    }

    public function test_crud_and_datatable_contract_markers_survive_visual_migration(): void
    {
        $crud = file_get_contents(
            resource_path('views/management/operations/resource.blade.php')
        );
        $portalIndex = file_get_contents(
            resource_path('views/portal/operations/index.blade.php')
        );
        $datatable = file_get_contents(
            resource_path('views/shared/server-datatable.blade.php')
        );

        foreach ([
            'id="buildinoCrudApp"',
            'data-resource="{{ $resourceKey }}"',
            'id="crudContextFields"',
            'data-context-name=',
            'id="crudSearch"',
            'id="crudTableBody"',
            'id="crudDrawer"',
            'id="crudForm"',
            'window.BuildinoCrud',
            'js/buildino-crud.js',
        ] as $marker) {
            $this->assertStringContainsString($marker, $crud);
        }

        foreach ([
            'data-dt-filter-scope',
            'data-dt-filter="status"',
            'data-dt-filter="from"',
            'data-dt-filter="to"',
            'data-dt-reset',
            "'shared.server-datatable'",
        ] as $marker) {
            $this->assertStringContainsString($marker, $portalIndex);
        }

        foreach ([
            'data-dt-shell',
            'data-dt-loading',
            'data-dt-url="{{ $url }}"',
            'data-dt-columns="{{ $encodedColumns }}"',
            'data-dt-page-length=',
            'js-server-datatable',
        ] as $marker) {
            $this->assertStringContainsString($marker, $datatable);
        }
    }
}
