<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class EnsureUserIsActiveTest extends TestCase
{
    public function test_active_user_can_continue(): void
    {
        $user = new User(['is_active' => true, 'is_blocked' => false]);

        $request = Request::create('/api/v1/test');
        $request->setUserResolver(fn () => $user);

        $response = app(EnsureUserIsActive::class)->handle(
            $request,
            fn () => response()->json(['ok' => true]),
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function test_blocked_user_is_denied(): void
    {
        $user = new User(['is_active' => true, 'is_blocked' => true]);

        $request = Request::create('/api/v1/test');
        $request->setUserResolver(fn () => $user);

        $response = app(EnsureUserIsActive::class)->handle(
            $request,
            fn () => response()->json(['ok' => true]),
        );

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }
}
