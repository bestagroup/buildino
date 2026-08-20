<?php

namespace Tests\Feature\System;

use Tests\TestCase;

class AuthenticationResponsiveVisualQaTest extends TestCase
{
    public function test_authentication_views_share_materialize_theme_and_accessible_theme_control(): void
    {
        foreach ([
            resource_path('views/management/auth/login.blade.php'),
            resource_path('views/management/auth/forgot-password.blade.php'),
            resource_path('views/management/auth/reset-password.blade.php'),
            resource_path('views/portal/auth/login.blade.php'),
        ] as $path) {
            $contents = file_get_contents($path);

            foreach ([
                'name="color-scheme" content="light dark"',
                'assets/vendor/css/rtl/core.css',
                'assets/vendor/css/rtl/theme-default.css',
                'css/buildino-materialize.css',
                'js/buildino-foundation.js',
                'js/buildino-materialize.js',
                'materialize-auth',
                'materialize-auth-theme-toggle',
                'data-materialize-theme-toggle',
                'aria-pressed="false"',
            ] as $marker) {
                $this->assertStringContainsString($marker, $contents, $path.' is missing '.$marker);
            }
        }
    }

    public function test_canonical_materialize_css_contains_mobile_viewport_table_modal_and_accessibility_guards(): void
    {
        $css = file_get_contents(public_path('css/buildino-materialize.css'));

        foreach ([
            'Buildino v3.10 — Authentication / Responsive / Cross-browser Visual QA',
            'min-height: 100dvh',
            'safe-area-inset-top',
            'body.buildino-scroll-locked',
            ':focus-visible',
            '-webkit-overflow-scrolling: touch',
            'max-height: calc(100dvh - 2rem)',
            '.materialize-auth-theme-toggle',
            'font-size: 16px !important',
            '@supports not ((backdrop-filter: blur(2px))',
            '@media (forced-colors: active)',
        ] as $marker) {
            $this->assertStringContainsString($marker, $css);
        }
    }

    public function test_shell_controller_keeps_one_menu_state_with_focus_and_system_theme_support(): void
    {
        $js = file_get_contents(public_path('js/buildino-materialize.js'));

        foreach ([
            "window.matchMedia('(prefers-color-scheme: dark)')",
            "root.classList.toggle('layout-menu-expanded')",
            "body.classList.toggle('buildino-scroll-locked', expanded)",
            "button.setAttribute('aria-expanded'",
            "menu.setAttribute('aria-hidden'",
            "lastMenuTrigger.focus({ preventScroll: true })",
            'typeof query.addListener === \'function\'',
            'window.requestAnimationFrame',
        ] as $marker) {
            $this->assertStringContainsString($marker, $js);
        }
    }

    public function test_management_and_portal_shells_publish_menu_accessibility_contract(): void
    {
        foreach ([
            resource_path('views/management/layouts/app.blade.php'),
            resource_path('views/portal/layouts/app.blade.php'),
        ] as $path) {
            $contents = file_get_contents($path);

            foreach ([
                'data-materialize-overlay',
                'aria-hidden="true"',
                'data-materialize-menu-toggle',
                'aria-expanded="false"',
                'aria-controls="layout-menu"',
                'data-materialize-theme-toggle',
                'aria-pressed="false"',
            ] as $marker) {
                $this->assertStringContainsString($marker, $contents, $path.' is missing '.$marker);
            }
        }
    }
}
