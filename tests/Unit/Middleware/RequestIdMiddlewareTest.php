<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\RequestIdMiddleware;
use Illuminate\Http\Request;
use Tests\TestCase;

class RequestIdMiddlewareTest extends TestCase
{
    public function test_request_id_is_added_to_response(): void
    {
        $request = Request::create('/api/v1/test');

        $response = app(RequestIdMiddleware::class)->handle(
            $request,
            fn () => response()->json(['ok' => true]),
        );

        $this->assertNotEmpty($response->headers->get('X-Request-ID'));
        $this->assertSame(
            $request->attributes->get('request_id'),
            $response->headers->get('X-Request-ID'),
        );
    }
}
