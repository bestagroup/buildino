<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyApiSecurityHeaders
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set(
            'X-Buildino-API-Version',
            (string) config(
                'api_contract_v1.version',
                '1.0.0'
            )
        );

        $response->headers->set(
            'X-Content-Type-Options',
            'nosniff'
        );

        $response->headers->set(
            'X-Frame-Options',
            'DENY'
        );

        $response->headers->set(
            'Referrer-Policy',
            'no-referrer'
        );

        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=()'
        );

        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'"
        );

        if (
            app()->environment('production')
            && $request->isSecure()
        ) {
            $maxAge = (int) config(
                'production_readiness.security.hsts_max_age',
                31536000
            );

            $value =
                'max-age='.$maxAge;

            if (
                config(
                    'production_readiness.security.hsts_include_subdomains',
                    true
                )
            ) {
                $value .=
                    '; includeSubDomains';
            }

            $response->headers->set(
                'Strict-Transport-Security',
                $value
            );
        }

        return $response;
    }
}
