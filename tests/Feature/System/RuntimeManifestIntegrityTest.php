<?php

namespace Tests\Feature\System;

use Tests\TestCase;

class RuntimeManifestIntegrityTest extends TestCase
{
    public function test_runtime_manifests_required_for_reproducible_build_are_present(): void
    {
        foreach ([
            'artisan',
            'composer.json',
            'phpunit.xml',
            '.env.example',
            'package.json',
            'vite.config.js',
        ] as $file) {
            $this->assertFileExists(
                base_path($file),
                "Required runtime manifest [{$file}] is missing."
            );
        }
    }

    public function test_composer_manifest_preserves_required_buildino_runtime_packages(): void
    {
        $composer = json_decode(
            (string) file_get_contents(base_path('composer.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $required = $composer['require'] ?? [];

        $this->assertArrayHasKey('laravel/framework', $required);
        $this->assertArrayHasKey('laravel/sanctum', $required);
        $this->assertArrayHasKey('yajra/laravel-datatables-oracle', $required);
        $this->assertArrayHasKey('morilog/jalali', $required);
        $this->assertArrayHasKey('darkaonline/l5-swagger', $required);

        $this->assertStringContainsString('12', (string) $required['laravel/framework']);
        $this->assertStringContainsString('4', (string) $required['laravel/sanctum']);
        $this->assertStringContainsString('12', (string) $required['yajra/laravel-datatables-oracle']);
    }

    public function test_phpunit_is_forced_to_sqlite_memory_and_non_external_notification_drivers(): void
    {
        // Assert the effective Laravel test runtime instead of coupling this
        // regression test to a specific phpunit.xml/phpunit.xml.dist layout.
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('sync', config('queue.default'));
        $this->assertSame('log', config('notifications.sms_provider'));
        $this->assertSame('log', config('notifications.push_provider'));
    }
}
