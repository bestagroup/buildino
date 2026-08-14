<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $candidate = trim(
            (string) $request->header(
                'X-Request-ID',
                ''
            )
        );

        /*
         * Never reflect arbitrary header content back to logs/clients.
         * Accept only a bounded, log-safe correlation identifier.
         */
        $requestId = preg_match(
            '/\A[A-Za-z0-9._:-]{8,128}\z/',
            $candidate
        )
            ? $candidate
            : (string) Str::uuid();

        $request->attributes->set(
            'request_id',
            $requestId
        );

        $response = $next(
            $request
        );

        $response->headers->set(
            'X-Request-ID',
            $requestId
        );

        return $response;
    }
}
