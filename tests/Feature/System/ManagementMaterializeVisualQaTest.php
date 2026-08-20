<?php

namespace Tests\Feature\System;

use Tests\TestCase;

class ManagementMaterializeVisualQaTest extends TestCase
{
    public function test_management_dashboard_uses_materialize_card_language_without_new_theme_layer(): void
    {
        $layout = file_get_contents(
            resource_path('views/management/layouts/app.blade.php')
        );

        $dashboard = file_get_contents(
            resource_path('views/management/dashboard.blade.php')
        );

        foreach ([
            'buildino-dashboard-overview',
            'buildino-dashboard-role',
            'buildino-stat-grid',
            'buildino-kpi-grid',
            'buildino-section-card',
            'card h-100',
        ] as $marker) {
            $this->assertStringContainsString($marker, $dashboard);
        }

        $this->assertStringContainsString(
            'css/buildino-materialize.css',
            $layout
        );

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

    public function test_operations_pages_keep_runtime_contracts_while_using_native_controls(): void
    {
        $index = file_get_contents(
            resource_path('views/management/operations/index.blade.php')
        );

        $resource = file_get_contents(
            resource_path('views/management/operations/resource.blade.php')
        );

        foreach ([
            'buildino-operations-overview',
            'buildino-operations-groups',
            'crud-resource-card card h-100',
        ] as $marker) {
            $this->assertStringContainsString($marker, $index);
        }

        foreach ([
            'id="buildinoCrudApp"',
            'id="crudCreateButton"',
            'id="crudContextFields"',
            'id="crudSearch"',
            'id="crudPageSize"',
            'id="crudRefreshButton"',
            'id="crudTableHead"',
            'id="crudTableBody"',
            'id="crudDrawer"',
            'id="crudForm"',
            'id="crudActionModal"',
            'data-context-name=',
            'data-lookup=',
            'data-required=',
        ] as $contractMarker) {
            $this->assertStringContainsString(
                $contractMarker,
                $resource
            );
        }

        foreach ([
            'class="form-control"',
            'class="form-select"',
            'table table-hover align-middle mb-0',
            'btn btn-primary',
            'btn btn-label-secondary',
        ] as $visualMarker) {
            $this->assertStringContainsString(
                $visualMarker,
                $resource
            );
        }
    }

    public function test_dynamic_crud_fields_receive_materialize_bootstrap_control_classes(): void
    {
        $crudJs = file_get_contents(
            public_path('js/buildino-crud.js')
        );

        foreach ([
            'input.classList.add("form-select")',
            'input.classList.add("form-check-input")',
            'input.classList.add("form-control")',
        ] as $marker) {
            $this->assertStringContainsString(
                $marker,
                $crudJs
            );
        }

        // Data identity and request payload binding continue to come from
        // the configured field name, not from visual classes.
        $this->assertStringContainsString(
            "input.name =\n            field.name;",
            $crudJs
        );

        $this->assertStringContainsString(
            'input.dataset.fieldType',
            $crudJs
        );
    }
}
