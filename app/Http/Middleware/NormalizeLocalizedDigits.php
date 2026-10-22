<?php

namespace App\Http\Middleware;

use App\Support\Input\DigitNormalizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class NormalizeLocalizedDigits
{
    /**
     * Values that must remain byte-for-byte identical to what the client sent.
     *
     * @var array<int, string>
     */
    private const SENSITIVE_KEYS = [
        '_token',
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'new_password_confirmation',
        'token',
        'access_token',
        'refresh_token',
        'api_token',
        'api_key',
        'secret',
        'signature',
        'hash',
    ];

    public function __construct(
        private readonly DigitNormalizer $digits
    ) {}

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $request->query->replace(
            $this->normalizeArray(
                $request->query->all()
            )
        );

        if ($request->isJson()) {
            $request->json()->replace(
                $this->normalizeArray(
                    $request->json()->all()
                )
            );
        } else {
            $request->request->replace(
                $this->normalizeArray(
                    $request->request->all()
                )
            );
        }

        return $next($request);
    }

    /**
     * @param  array<string|int, mixed>  $values
     * @return array<string|int, mixed>
     */
    private function normalizeArray(array $values): array
    {
        foreach ($values as $key => $value) {
            if (
                is_string($key)
                && $this->isSensitiveKey($key)
            ) {
                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->normalizeArray(
                    $value
                );

                continue;
            }

            if (is_string($value)) {
                $values[$key] = $this->digits
                    ->normalize($value);
            }
        }

        return $values;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        if (in_array($key, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        return str_contains($key, 'password')
            || str_ends_with($key, '_token')
            || str_ends_with($key, '_secret')
            || str_ends_with($key, '_signature')
            || str_ends_with($key, '_hash')
            || str_ends_with($key, '_api_key');
    }
}
