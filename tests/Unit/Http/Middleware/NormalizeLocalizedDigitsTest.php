<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\NormalizeLocalizedDigits;
use App\Support\Input\DigitNormalizer;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class NormalizeLocalizedDigitsTest extends TestCase
{
    public function test_it_normalizes_nested_form_and_query_values(): void
    {
        $request = Request::create(
            '/submit?page=۱۲',
            'POST',
            [
                'mobile' => '۰۹۱۲۳۴۵۶۷۸۹',
                'amount' => '١٢٣٤٥',
                'nested' => [
                    'unit_number' => '۳۰۵',
                ],
            ]
        );

        $middleware = new NormalizeLocalizedDigits(
            new DigitNormalizer()
        );

        $middleware->handle(
            $request,
            fn (): Response => new Response('ok')
        );

        $this->assertSame(
            '09123456789',
            $request->input('mobile')
        );
        $this->assertSame(
            '12345',
            $request->input('amount')
        );
        $this->assertSame(
            '305',
            $request->input('nested.unit_number')
        );
        $this->assertSame(
            '12',
            $request->query('page')
        );
    }

    public function test_it_preserves_sensitive_credentials_but_normalizes_invitation_codes(): void
    {
        $request = Request::create(
            '/submit',
            'POST',
            [
                'password' => 'Secret۱۲۳',
                'password_confirmation' => 'Secret۱۲۳',
                'invitation_token' => 'invite-۱۲۳',
                'api_key' => 'key-۱۲۳',
                'code' => '۱۲۳۴۵۶',
            ]
        );

        $middleware = new NormalizeLocalizedDigits(
            new DigitNormalizer()
        );

        $middleware->handle(
            $request,
            fn (): Response => new Response('ok')
        );

        $this->assertSame(
            'Secret۱۲۳',
            $request->input('password')
        );
        $this->assertSame(
            'Secret۱۲۳',
            $request->input('password_confirmation')
        );
        $this->assertSame(
            'invite-123',
            $request->input('invitation_token')
        );
        $this->assertSame(
            'key-۱۲۳',
            $request->input('api_key')
        );
        $this->assertSame(
            '123456',
            $request->input('code')
        );
    }
}
