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

    public function test_portal_login_loads_materialize_core_and_shared_ui_foundation(): void
    {
        $response = $this->get('/portal/login')
            ->assertOk()
            ->assertSee('buildino-foundation.css', false)
            ->assertSee('buildino-foundation.js', false)
            ->assertSee('data-buildino-submit', false);

        $response
            ->assertSee('assets/vendor/css/rtl/core.css', false)
            ->assertSee('css/buildino-materialize.css', false)
            ->assertDontSee(
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
            '^3.5',
            config('management_ui.libraries.morilog_jalali.version_constraint')
        );
    }
}
