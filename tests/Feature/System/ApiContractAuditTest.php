<?php

namespace Tests\Feature\System;

use App\Services\ApiContract\ApiContractService;
use Tests\TestCase;

class ApiContractAuditTest extends TestCase
{
    public function test_v1_contract_has_no_security_or_route_drift(): void
    {
        $result = app(
            ApiContractService::class
        )->audit();

        $this->assertSame(
            [],
            $result['duplicates'],
            'Duplicate V1 route contracts: '
            .json_encode(
                $result['duplicates'],
                JSON_UNESCAPED_SLASHES
            )
        );

        $this->assertSame(
            [],
            $result['unexpected_public_routes'],
            'Unexpected public V1 routes: '
            .json_encode(
                $result['unexpected_public_routes'],
                JSON_UNESCAPED_SLASHES
            )
        );

        $this->assertSame(
            [],
            $result['missing_allowed_public_routes'],
            'Expected public V1 routes are missing: '
            .json_encode(
                $result['missing_allowed_public_routes'],
                JSON_UNESCAPED_SLASHES
            )
        );

        $this->assertSame(
            [],
            $result['missing_critical_routes'],
            'Critical V1 routes are missing: '
            .json_encode(
                $result['missing_critical_routes'],
                JSON_UNESCAPED_SLASHES
            )
        );

        $this->assertSame(
            [],
            $result['security_mismatches'],
            'Critical route public/protected mismatch: '
            .json_encode(
                $result['security_mismatches'],
                JSON_UNESCAPED_SLASHES
            )
        );

        $this->assertSame(
            [],
            $result['protected_without_identity_guards'],
            'Protected routes missing user.active / identity.verified: '
            .json_encode(
                $result['protected_without_identity_guards'],
                JSON_UNESCAPED_SLASHES
            )
        );

        $this->assertTrue(
            $result['ok'],
            json_encode(
                $result,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
            )
        );

        $this->assertGreaterThan(
            100,
            $result['route_count']
        );

        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/',
            $result['contract_hash']
        );
    }

    public function test_openapi_and_postman_are_derived_from_same_route_catalog(): void
    {
        $service = app(
            ApiContractService::class
        );

        $catalog =
            $service->catalog();

        $openApi =
            $service->openApi();

        $postman =
            $service
                ->postmanCollection();

        $this->assertSame(
            '3.0.3',
            $openApi['openapi']
        );

        $this->assertSame(
            config(
                'api_contract_v1.version'
            ),
            $openApi['info']['version']
        );

        $this->assertGreaterThan(
            100,
            count(
                $openApi['paths']
            )
        );

        $postmanRequests =
            collect(
                $postman['item']
            )
                ->sum(
                    fn (array $folder): int =>
                        count(
                            $folder['item']
                        )
                );

        $this->assertSame(
            count($catalog),
            $postmanRequests
        );

        $uploadOperation = $openApi['paths'][
            '/documents/{document}/files'
        ]['post'];

        $this->assertArrayHasKey(
            'multipart/form-data',
            $uploadOperation['requestBody']['content']
        );

        $fileParameter = $openApi['paths'][
            '/files/{file}/download'
        ]['get']['parameters'][0];

        $this->assertSame(
            'uuid',
            $fileParameter['schema']['format']
        );
    }
}
