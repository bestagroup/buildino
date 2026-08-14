<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\EnsureVerifiedIdentity;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class EnsureVerifiedIdentityTest extends TestCase
{
    public function test_unverified_user_is_denied(): void
    {
        config()->set('api_security.require_verified_identity', true);

        $user = new User([
            'is_active' => true,
            'is_blocked' => false,
            'email_verified_at' => null,
            'mobile_verified_at' => null,
        ]);

        $request = Request::create('/api/v1/test');
        $request->setUserResolver(fn () => $user);

        $response = app(EnsureVerifiedIdentity::class)->handle(
            $request,
            fn () => response()->json(['ok' => true]),
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_mobile_verified_user_can_continue(): void
    {
        config()->set('api_security.require_verified_identity', true);

        $user = new User([
            'is_active' => true,
            'is_blocked' => false,
            'mobile_verified_at' => now(),
        ]);

        $request = Request::create('/api/v1/test');
        $request->setUserResolver(fn () => $user);

        $response = app(EnsureVerifiedIdentity::class)->handle(
            $request,
            fn () => response()->json(['ok' => true]),
        );

        $this->assertSame(200, $response->getStatusCode());
    }
}
