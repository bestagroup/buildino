<?php

namespace Tests\Feature\System;

use Tests\TestCase;

class PortalMaterializeVisualQaTest extends TestCase
{
    public function test_resident_and_provider_dashboards_use_canonical_materialize_cards(): void
    {
        foreach ([
            resource_path('views/portal/resident/dashboard.blade.php'),
            resource_path('views/portal/provider/dashboard.blade.php'),
        ] as $path) {
            $view = file_get_contents($path);

            foreach ([
                'portal-hero',
                'portal-stat-card card',
                'portal-section card',
                'btn btn-primary',
            ] as $marker) {
                $this->assertStringContainsString(
                    $marker,
                    $view,
                    $path.' is missing '.$marker
                );
            }
        }
    }

    public function test_portal_operation_filters_use_native_controls_without_changing_filter_contract(): void
    {
        $index = file_get_contents(
            resource_path('views/portal/operations/index.blade.php')
        );

        foreach ([
            'data-dt-filter="status"',
            'data-dt-filter="from"',
            'data-dt-filter="to"',
            'data-dt-reset',
            'form-select form-select-sm',
            'form-control form-control-sm',
            'btn btn-label-secondary',
            'portal-datatable-card card',
        ] as $marker) {
            $this->assertStringContainsString($marker, $index);
        }
    }

    public function test_portal_detail_page_keeps_operation_contract_with_materialize_cards(): void
    {
        $detail = file_get_contents(
            resource_path('views/portal/operations/show.blade.php')
        );

        foreach ([
            'portal-detail-hero card',
            'portal-detail-facts',
            'portal-section card',
            'portal-table-card card',
            'portal-detail-timeline',
        ] as $marker) {
            $this->assertStringContainsString($marker, $detail);
        }

        foreach ([
            'route("portal.{$area}.operations.index"',
            '$detail[\'facts\']',
            '$detail[\'sections\']',
        ] as $contractMarker) {
            $this->assertStringContainsString(
                $contractMarker,
                $detail
            );
        }
    }

    public function test_portal_visual_qa_remains_inside_canonical_materialize_layer(): void
    {
        $layout = file_get_contents(
            resource_path('views/portal/layouts/app.blade.php')
        );

        $css = file_get_contents(
            public_path('css/buildino-materialize.css')
        );

        $this->assertStringContainsString(
            'css/buildino-materialize.css',
            $layout
        );

        foreach ([
            'Buildino v3.9 - Resident & Provider Portal Visual QA',
            '.materialize-buildino .portal-stat-grid',
            '.materialize-buildino .portal-unit-grid',
            '.materialize-buildino .portal-job-grid',
            '.materialize-buildino .portal-datatable-toolbar',
            '.materialize-buildino .portal-detail-facts',
            '.materialize-buildino .portal-modal',
        ] as $marker) {
            $this->assertStringContainsString($marker, $css);
        }

        foreach ([
            'buildino-materio-phase2.css',
            'buildino-materio-phase3.css',
            'buildino-materio-shell-recovery.css',
        ] as $legacyLayer) {
            $this->assertStringNotContainsString(
                $legacyLayer,
                $layout
            );
        }
    }
}
