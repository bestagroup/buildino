<?php

namespace Tests\Feature\Web;

use Tests\TestCase;

class UiFoundationWebTest extends TestCase
{
    public function test_management_login_loads_shared_ui_foundation(): void
    {
        $this->get('/management/login')
            ->assertOk()
            ->assertSee('buildino-foundation.css', false)
            ->assertSee('buildino-foundation.js', false)
            ->assertSee('data-buildino-submit', false);
    }

    public function test_portal_login_loads_bootstrap_and_shared_ui_foundation(): void
    {
        $response = $this->get('/portal/login')
            ->assertOk()
            ->assertSee('buildino-foundation.css', false)
            ->assertSee('buildino-foundation.js', false)
            ->assertSee('data-buildino-submit', false);

        $response->assertSee(
            (string) config('management_ui.libraries.bootstrap.css'),
            false
        );
    }

    public function test_required_ui_library_versions_are_pinned(): void
    {
        $this->assertSame(
            '5.3.8',
            config('management_ui.libraries.bootstrap.version')
        );

        $this->assertSame(
            '11.26.25',
            config('management_ui.libraries.sweetalert2.version')
        );

        $this->assertSame(
            '2.3.8',
            config('management_ui.libraries.datatables.version')
        );

        $this->assertSame(
            '4.1.0',
            config('management_ui.libraries.select2.version')
        );

        $this->assertSame(
            '3.7.1',
            config('management_ui.libraries.select2.jquery_version')
        );

        $this->assertSame(
            '1.0.0',
            config('management_ui.libraries.jalali_datepicker.version')
        );

        $this->assertSame(
            '2.0.1',
            config('management_ui.libraries.jalali_datepicker.converter_version')
        );

        $this->assertSame(
            '^3.5',
            config('management_ui.libraries.morilog_jalali.version_constraint')
        );
    }

    public function test_internal_panels_contain_horizontal_overflow_without_disabling_table_scroll(): void
    {
        $management = preg_replace(
            '/\s+/',
            ' ',
            (string) file_get_contents(
                public_path('css/buildino-management.css')
            )
        );
        $portal = preg_replace(
            '/\s+/',
            ' ',
            (string) file_get_contents(
                public_path('css/buildino-portal.css')
            )
        );
        $crud = preg_replace(
            '/\s+/',
            ' ',
            (string) file_get_contents(
                public_path('css/buildino-crud.css')
            )
        );

        foreach ([$management, $portal] as $layoutCss) {
            $this->assertStringContainsString(
                'overflow-x: clip;',
                $layoutCss
            );
            $this->assertStringContainsString(
                'max-width: 100%;',
                $layoutCss
            );
        }

        $this->assertStringContainsString(
            '.crud-drawer { position: fixed;',
            $crud
        );
        $this->assertStringContainsString(
            'max-width: 100vw;',
            $crud
        );
        $this->assertStringContainsString(
            'visibility: hidden; overflow-x: hidden;',
            $crud
        );
        $this->assertStringContainsString(
            'pointer-events: none; transform: translate3d(calc(-100% - 2px), 0, 0);',
            $crud
        );
        $this->assertStringContainsString(
            '.crud-drawer.is-open { visibility: visible; pointer-events: auto;',
            $crud
        );
        $this->assertStringContainsString(
            '.crud-table-wrap { width: 100%; overflow-x: auto;',
            $crud
        );
    }

    public function test_all_web_selects_are_covered_by_the_global_searchable_select_layer(): void
    {
        $package = json_decode(
            (string) file_get_contents(base_path('package.json')),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $this->assertSame(
            '4.1.0',
            data_get($package, 'dependencies.select2')
        );
        $this->assertSame(
            '3.7.1',
            data_get($package, 'dependencies.jquery')
        );

        $entry = (string) file_get_contents(
            resource_path('js/app.js')
        );
        $enhancer = (string) file_get_contents(
            resource_path('js/buildino-select2.js')
        );

        $this->assertStringContainsString(
            "import './buildino-select2'",
            $entry
        );
        $this->assertStringContainsString(
            'minimumResultsForSearch: 0',
            $enhancer
        );
        $this->assertStringContainsString(
            'new MutationObserver',
            $enhancer
        );
        $this->assertStringContainsString(
            'window.BuildinoSelect2',
            $enhancer
        );
        $this->assertStringContainsString(
            "'.swal2-container select'",
            $enhancer
        );
        $this->assertStringContainsString(
            "'.swal2-popup select'",
            $enhancer
        );
        $this->assertStringContainsString(
            "'[role=\"alertdialog\"] select'",
            $enhancer
        );
        $this->assertStringContainsString(
            'if (select.matches(disabledSelector))',
            $enhancer
        );
        $this->assertStringContainsString(
            'destroy(select);',
            $enhancer
        );

        $bladeFiles = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                resource_path('views')
            )
        );

        foreach ($bladeFiles as $bladeFile) {
            if (
                ! $bladeFile->isFile()
                || ! str_ends_with(
                    $bladeFile->getFilename(),
                    '.blade.php'
                )
            ) {
                continue;
            }

            $path = $bladeFile->getPathname();
            $source = (string) file_get_contents($path);

            $this->assertStringNotContainsString(
                'data-select2="off"',
                $source,
                "Select2 is disabled in {$path}."
            );
            $this->assertStringNotContainsString(
                'data-native-select',
                $source,
                "A native select opt-out exists in {$path}."
            );
        }
    }

    public function test_all_web_date_inputs_are_covered_by_the_global_jalali_picker_layer(): void
    {
        $package = json_decode(
            (string) file_get_contents(base_path('package.json')),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $this->assertSame(
            '1.0.0',
            data_get(
                $package,
                'dependencies.@majidh1/jalalidatepicker'
            )
        );
        $this->assertSame(
            '2.0.1',
            data_get(
                $package,
                'dependencies.jalaali-js'
            )
        );

        $entry = (string) file_get_contents(
            resource_path('js/app.js')
        );
        $enhancer = (string) file_get_contents(
            resource_path(
                'js/buildino-jalali-datepicker.js'
            )
        );
        $crud = (string) file_get_contents(
            public_path('js/buildino-crud.js')
        );

        $this->assertStringContainsString(
            "import './buildino-jalali-datepicker'",
            $entry
        );

        foreach (
            [
                'input[type="date"]',
                'input[type="datetime-local"]',
                'input[type="time"]',
            ]
            as $selector
        ) {
            $this->assertStringContainsString(
                $selector,
                $enhancer
            );
        }

        $this->assertStringContainsString(
            'window.BuildinoJalaliDatepicker',
            $enhancer
        );
        $this->assertStringContainsString(
            'new MutationObserver',
            $enhancer
        );
        $this->assertStringContainsString(
            'buildinoDateType',
            $crud
        );
        $this->assertStringContainsString(
            'BuildinoJalaliDatepicker',
            $crud
        );

        $configuredDateFields = 0;
        $crudConfiguration =
            config(
                'management_crud',
                []
            );

        array_walk_recursive(
            $crudConfiguration,
            function ($value, $key) use (&$configuredDateFields): void {
                if (
                    $key === 'type'
                    && in_array(
                        $value,
                        [
                            'date',
                            'datetime-local',
                            'time',
                        ],
                        true
                    )
                ) {
                    $configuredDateFields++;
                }
            }
        );

        $this->assertGreaterThan(
            0,
            $configuredDateFields
        );

        $bladeDateInputs = 0;
        $bladeFiles = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                resource_path('views')
            )
        );

        foreach ($bladeFiles as $bladeFile) {
            if (
                ! $bladeFile->isFile()
                || ! str_ends_with(
                    $bladeFile->getFilename(),
                    '.blade.php'
                )
            ) {
                continue;
            }

            $path = $bladeFile->getPathname();
            $source = (string) file_get_contents($path);
            $bladeDateInputs += preg_match_all(
                '/type\s*=\s*["\'](?:date|datetime-local|time)["\']/',
                $source
            );

            $this->assertStringNotContainsString(
                'data-native-date',
                $source,
                "A native date opt-out exists in {$path}."
            );
            $this->assertStringNotContainsString(
                'data-jdp="off"',
                $source,
                "Jalali datepicker is disabled in {$path}."
            );
        }

        $this->assertGreaterThan(
            0,
            $bladeDateInputs
        );
    }
}
