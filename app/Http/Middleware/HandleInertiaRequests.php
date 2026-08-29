<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The transitional management shell used by Inertia responses.
     * Existing Blade routes continue to render their current views.
     *
     * @var string
     */
    protected $rootView = 'management.inertia';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn (): ?array => $request->user()
                    ? [
                        'id' => $request->user()->getKey(),
                        'name' => $request->user()->full_name
                            ?? $request->user()->name
                            ?? $request->user()->mobile,
                    ]
                    : null,
            ],
            'csrfToken' => fn (): string => csrf_token(),
        ];
    }
}
