<?php

namespace Tests\Feature\System;

use Tests\TestCase;

class MaterioUxPhase3IntegrationTest extends TestCase
{
    public function test_materialize_adapter_is_the_single_final_visual_layer(): void
    {
        $adapter = public_path('css/buildino-materialize.css');

        $this->assertFileExists($adapter);
        $this->assertGreaterThan(0, filesize($adapter));

        foreach ([
            resource_path('views/management/layouts/app.blade.php'),
            resource_path('views/portal/layouts/app.blade.php'),
        ] as $layoutPath) {
            $layout = file_get_contents($layoutPath);
            $stack = strpos($layout, "@stack('styles')");
            $adapterPosition = strpos($layout, 'css/buildino-materialize.css');

            $this->assertNotFalse($stack, $layoutPath);
            $this->assertNotFalse($adapterPosition, $layoutPath);
            $this->assertGreaterThan($stack, $adapterPosition, $layoutPath);
            $this->assertStringNotContainsString('buildino-materio-phase2.css', $layout);
            $this->assertStringNotContainsString('buildino-materio-phase3.css', $layout);
        }
    }

    public function test_shared_ui_namespace_keeps_foundation_and_management_capabilities(): void
    {
        $foundation = file_get_contents(public_path('js/buildino-foundation.js'));
        $management = file_get_contents(public_path('js/buildino-management.js'));

        foreach ([
            'confirm,',
            'setLoading,',
            'toast,',
            'clearValidationErrors,',
            'applyValidationErrors,',
        ] as $marker) {
            $this->assertStringContainsString($marker, $foundation);
        }

        $this->assertStringContainsString('...sharedUi,', $management);
        $this->assertStringContainsString('toFaDigits,', $management);
        $this->assertStringContainsString('number(value)', $management);
        $this->assertStringContainsString('dateTime(value)', $management);
    }

    public function test_crud_portal_and_datatables_use_shared_feedback_contract(): void
    {
        $crud = file_get_contents(public_path('js/buildino-crud.js'));
        $portal = file_get_contents(public_path('js/buildino-portal.js'));
        $datatables = file_get_contents(public_path('js/buildino-datatables.js'));

        $this->assertStringContainsString('applyApiValidation', $crud);
        $this->assertStringContainsString('error?.status !== 422', $crud);
        $this->assertStringContainsString('window.BuildinoUI.confirm', $crud);

        $this->assertStringContainsString('handleFormError', $portal);
        $this->assertStringContainsString('window.BuildinoUI.applyValidationErrors', $portal);
        $this->assertStringContainsString('window.BuildinoUI.setLoading', $portal);
        $this->assertStringContainsString('window.BuildinoUI.confirm', $portal);

        $this->assertStringContainsString('aria-busy', $datatables);
        $this->assertStringContainsString('window.BuildinoUI.toast', $datatables);
    }

    public function test_auth_pages_receive_materialize_visual_layer_and_shared_loading(): void
    {
        foreach ([
            resource_path('views/management/auth/login.blade.php'),
            resource_path('views/management/auth/forgot-password.blade.php'),
            resource_path('views/management/auth/reset-password.blade.php'),
            resource_path('views/portal/auth/login.blade.php'),
        ] as $path) {
            $contents = file_get_contents($path);

            $this->assertStringContainsString('css/buildino-materialize.css', $contents, $path);
            $this->assertStringContainsString('js/buildino-foundation.js', $contents, $path);
            $this->assertStringContainsString('js/buildino-materialize.js', $contents, $path);
            $this->assertStringContainsString('materialize-auth', $contents, $path);
        }
    }
}
