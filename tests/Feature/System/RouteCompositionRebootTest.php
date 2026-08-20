<?php

namespace Tests\Feature\System;

use App\Services\ApiContract\ApiContractService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteCompositionRebootTest extends TestCase
{
    public function test_additive_v1_routes_survive_application_reboot_in_same_php_process(): void
    {
        $before = app(
            ApiContractService::class
        )->catalog();

        $beforeCount = count(
            $before
        );

        $this->assertGreaterThan(
            100,
            $beforeCount,
            'Expected the complete Buildino V1 route surface before reboot.'
        );

        $this->assertCriticalRoutesPresent();

        /*
         * Recreate the Laravel application while PHPUnit remains in the same
         * PHP process. This is the exact lifecycle where require_once caused
         * additive route files to disappear.
         */
        $this->refreshApplication();

        $after = app(
            ApiContractService::class
        )->catalog();

        $afterCount = count(
            $after
        );

        $this->assertSame(
            $beforeCount,
            $afterCount,
            'V1 route count changed after Laravel application reboot.'
        );

        $this->assertGreaterThan(
            100,
            $afterCount
        );

        $this->assertCriticalRoutesPresent();

        $audit = app(
            ApiContractService::class
        )->audit();

        $this->assertSame(
            [],
            $audit['missing_allowed_public_routes']
        );

        $this->assertSame(
            [],
            $audit['missing_critical_routes']
        );
    }

    private function assertCriticalRoutesPresent(): void
    {
        $cases = [
            [
                'GET',
                '/api/v1/wallets/me',
            ],
            [
                'GET',
                '/api/v1/system/readiness',
            ],
            [
                'GET',
                '/api/v1/buildings/1/reports/financial-summary',
            ],
            [
                'POST',
                '/api/v1/buildings/1/wallet-topups',
            ],
            [
                'POST',
                '/api/v1/payment-gateways/fake/webhook',
            ],
            [
                'POST',
                '/api/v1/support-tickets/1/messages',
            ],
            [
                'PUT',
                '/api/v1/notification-preferences',
            ],
            [
                'POST',
                '/api/v1/service-requests/1/assign',
            ],
        ];

        foreach ($cases as [$method, $uri]) {
            $route = Route::getRoutes()->match(
                Request::create(
                    $uri,
                    $method
                )
            );

            $this->assertNotNull(
                $route,
                "{$method} {$uri} is not registered."
            );
        }
    }
}
