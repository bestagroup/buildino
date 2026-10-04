<?php

namespace Tests\Feature\System;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackendHardeningRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_async_queue_connections_default_to_after_commit(): void
    {
        foreach ([
            'database',
            'redis',
            'sqs',
            'beanstalkd',
        ] as $connection) {
            $this->assertTrue(
                (bool) config(
                    "queue.connections.{$connection}.after_commit"
                ),
                "Queue connection [{$connection}] must dispatch after commit."
            );
        }
    }

    public function test_api_request_id_is_safely_reflected_for_correlation(): void
    {
        $requestId =
            'backend-hardening-12345';

        $this->withHeader(
            'X-Request-ID',
            $requestId
        )
            ->postJson(
                '/api/v1/auth/otp/request',
                [
                    'identifier' =>
                        '09121119999',
                    'channel' => 'sms',
                ]
            )
            ->assertAccepted()
            ->assertHeader(
                'X-Request-ID',
                $requestId
            );
    }

    public function test_unsafe_request_id_is_replaced(): void
    {
        $response = $this->withHeader(
            'X-Request-ID',
            'x'
        )
            ->postJson(
                '/api/v1/auth/otp/request',
                [
                    'identifier' =>
                        '09121119998',
                    'channel' => 'sms',
                ]
            )
            ->assertAccepted();

        $generated = (string) $response
            ->headers
            ->get('X-Request-ID');

        $this->assertNotSame(
            'x',
            $generated
        );

        $this->assertMatchesRegularExpression(
            '/\A[0-9a-f-]{36}\z/i',
            $generated
        );
    }
}
