<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->header('X-Request-ID');

        $requestId = config('api_security.trusted_request_id_header') && $this->valid($incoming)
            ? $incoming
            : (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);

        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }

    private function valid(?string $requestId): bool
    {
        return is_string($requestId)
            && strlen($requestId) <= 100
            && preg_match('/^[A-Za-z0-9._:-]+$/', $requestId) === 1;
    }
}
