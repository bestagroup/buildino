<?php

namespace Tests\Feature\System;

use Tests\TestCase;

class MaterioShellRecoveryTest extends TestCase
{
    public function test_management_and_portal_use_single_materialize_shell_contract(): void
    {
        $management = file_get_contents(
            resource_path('views/management/layouts/app.blade.php')
        );
        $managementSidebar = file_get_contents(
            resource_path('views/management/layouts/partials/sidebar.blade.php')
        );
        $portal = file_get_contents(
            resource_path('views/portal/layouts/app.blade.php')
        );
        $portalSidebar = file_get_contents(
            resource_path('views/portal/layouts/partials/sidebar.blade.php')
        );

        foreach ([$management, $portal] as $layout) {
            $this->assertStringContainsString('class="layout-container"', $layout);
            $this->assertStringContainsString('class="content-wrapper"', $layout);
            $this->assertStringContainsString('materialize-buildino', $layout);
            $this->assertStringContainsString('css/buildino-materialize.css', $layout);
            $this->assertStringContainsString('js/buildino-materialize.js', $layout);
            $this->assertStringContainsString('data-template="vertical-menu-template"', $layout);
        }

        foreach ([$managementSidebar, $portalSidebar] as $sidebar) {
            $this->assertStringContainsString('layout-menu menu-vertical menu bg-menu-theme', $sidebar);
            $this->assertStringContainsString('class="menu-inner py-1"', $sidebar);
            $this->assertStringContainsString('class="menu-header-text"', $sidebar);
            $this->assertStringContainsString('class="menu-link"', $sidebar);
            $this->assertStringContainsString('data-materialize-menu-collapse', $sidebar);
            $this->assertStringNotContainsString('nav-group__title', $sidebar);
            $this->assertStringNotContainsString('nav-group__items', $sidebar);
        }
    }

    public function test_materialize_shell_uses_one_state_model_and_no_legacy_theme_runtime(): void
    {
        $css = file_get_contents(public_path('css/buildino-materialize.css'));
        $js = file_get_contents(public_path('js/buildino-materialize.js'));

        $this->assertStringContainsString('layout-menu-collapsed', $css);
        $this->assertStringContainsString('layout-menu-expanded', $css);
        $this->assertStringContainsString("storageKeyCollapsed = 'buildino-materialize-menu-collapsed'", $js);
        $this->assertStringContainsString("root.classList.toggle('layout-menu-collapsed')", $js);
        $this->assertStringContainsString("root.classList.toggle('layout-menu-expanded')", $js);
        $this->assertStringContainsString("body.classList.remove('sidebar-open'", $js);

        foreach ([
            resource_path('views/management/layouts/app.blade.php'),
            resource_path('views/portal/layouts/app.blade.php'),
        ] as $layoutPath) {
            $layout = file_get_contents($layoutPath);

            $this->assertStringContainsString('css/buildino-materialize.css', $layout, $layoutPath);
            $this->assertStringContainsString('js/buildino-materialize.js', $layout, $layoutPath);
            $this->assertStringNotContainsString('buildino-materio-shell-recovery', $layout, $layoutPath);
            $this->assertStringNotContainsString('buildino-template.js', $layout, $layoutPath);
        }
    }

    public function test_portal_operation_sidebar_keeps_resource_route_contract(): void
    {
        foreach ([
            resource_path('views/portal/operations/index.blade.php'),
            resource_path('views/portal/operations/show.blade.php'),
        ] as $path) {
            $contents = file_get_contents($path);

            $this->assertStringContainsString("['resource' => \$key]", $contents);
            $this->assertStringContainsString("\$resource === \$key ? 'active' : ''", $contents);
            $this->assertStringContainsString('class="menu-link"', $contents);
        }
    }
}
