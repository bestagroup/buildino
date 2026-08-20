<?php

namespace App\Services\ApiContract;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

final class ApiContractService
{
    public function catalog(): array
    {
        $rows = [];

        foreach (
            RouteFacade::getRoutes()
            as $route
        ) {
            $uri = ltrim(
                $route->uri(),
                '/'
            );

            if (
                ! str_starts_with(
                    $uri,
                    'api/v1'
                )
            ) {
                continue;
            }

            $middleware = array_values(
                array_map(
                    static fn (mixed $item): string =>
                        is_string($item)
                            ? $item
                            : (
                                is_object($item)
                                    ? $item::class
                                    : (string) $item
                            ),
                    $route->gatherMiddleware()
                )
            );

            $protected =
                $this->isProtected(
                    $middleware
                );

            foreach (
                $this->httpMethods($route)
                as $method
            ) {
                $rows[] = [
                    'method' => $method,
                    'uri' => $uri,
                    'operation_id' =>
                        $this->operationId(
                            $method,
                            $uri
                        ),
                    'tag' =>
                        $this->tagFor(
                            $uri
                        ),
                    'protected' =>
                        $protected,
                    'middleware' =>
                        $middleware,
                    'action' =>
                        $route->getActionName(),
                    'request_example' =>
                        config(
                            'api_contract_v1.request_examples.'
                            .$method.' '.$uri
                        ),
                ];
            }
        }

        usort(
            $rows,
            static fn (
                array $left,
                array $right
            ): int =>
                [
                    $left['uri'],
                    $left['method'],
                ]
                <=>
                [
                    $right['uri'],
                    $right['method'],
                ]
        );

        return $rows;
    }

    public function audit(): array
    {
        $catalog = $this->catalog();

        $keys = array_map(
            fn (array $route): string =>
                $this->routeKey(
                    $route['method'],
                    $route['uri']
                ),
            $catalog
        );

        $duplicates = collect(
            $keys
        )
            ->countBy()
            ->filter(
                static fn (int $count): bool =>
                    $count > 1
            )
            ->keys()
            ->values()
            ->all();

        $allowedPublic = collect(
            config(
                'api_contract_v1.allowed_public_routes',
                []
            )
        )
            ->map(
                fn (string $key): string =>
                    $this->normalizeRouteKey(
                        $key
                    )
            )
            ->values();

        $actualPublic = collect(
            $catalog
        )
            ->filter(
                static fn (array $route): bool =>
                    ! $route['protected']
            )
            ->map(
                fn (array $route): string =>
                    $this->routeKey(
                        $route['method'],
                        $route['uri']
                    )
            )
            ->values();

        $unexpectedPublic =
            $actualPublic
                ->diff(
                    $allowedPublic
                )
                ->values()
                ->all();

        $missingAllowedPublic =
            $allowedPublic
                ->diff(
                    $actualPublic
                )
                ->values()
                ->all();

        $catalogByKey = collect(
            $catalog
        )->keyBy(
            fn (array $route): string =>
                $this->routeKey(
                    $route['method'],
                    $route['uri']
                )
        );

        $missingCritical = [];
        $securityMismatches = [];

        foreach (
            config(
                'api_contract_v1.critical_routes',
                []
            )
            as $expected
        ) {
            $key =
                $this->routeKey(
                    (string) $expected['method'],
                    (string) $expected['uri']
                );

            $actual =
                $catalogByKey->get(
                    $key
                );

            if (! $actual) {
                $missingCritical[] =
                    $key;

                continue;
            }

            if (
                (bool) $actual['protected']
                !== (bool) $expected['protected']
            ) {
                $securityMismatches[] = [
                    'route' => $key,
                    'expected_protected' =>
                        (bool) $expected['protected'],
                    'actual_protected' =>
                        (bool) $actual['protected'],
                ];
            }
        }

        $protectedWithoutIdentityGuard =
            collect($catalog)
                ->filter(
                    function (
                        array $route
                    ): bool {
                        if (
                            ! $route['protected']
                        ) {
                            return false;
                        }

                        $middleware =
                            $route['middleware'];

                        $hasActiveGuard =
                            $this->hasMiddleware(
                                $middleware,
                                [
                                    'user.active',
                                    \App\Http\Middleware\EnsureUserIsActive::class,
                                ]
                            );

                        $hasIdentityGuard =
                            $this->hasMiddleware(
                                $middleware,
                                [
                                    'identity.verified',
                                    \App\Http\Middleware\EnsureVerifiedIdentity::class,
                                ]
                            );

                        return ! $hasActiveGuard
                            || ! $hasIdentityGuard;
                    }
                )
                ->map(
                    static fn (array $route): string =>
                        $route['method']
                        .' '
                        .$route['uri']
                )
                ->values()
                ->all();

        $issues =
            count($duplicates)
            + count($unexpectedPublic)
            + count($missingAllowedPublic)
            + count($missingCritical)
            + count($securityMismatches)
            + count(
                $protectedWithoutIdentityGuard
            );

        return [
            'ok' => $issues === 0,

            'version' =>
                config(
                    'api_contract_v1.version',
                    '1.0.0'
                ),

            'route_count' =>
                count($catalog),

            'contract_hash' =>
                hash(
                    'sha256',
                    json_encode(
                        array_map(
                            static fn (
                                array $route
                            ): array => [
                                'method' =>
                                    $route['method'],
                                'uri' =>
                                    $route['uri'],
                                'protected' =>
                                    $route['protected'],
                            ],
                            $catalog
                        ),
                        JSON_UNESCAPED_SLASHES
                        | JSON_THROW_ON_ERROR
                    )
                ),

            'duplicates' =>
                $duplicates,

            'unexpected_public_routes' =>
                $unexpectedPublic,

            'missing_allowed_public_routes' =>
                $missingAllowedPublic,

            'missing_critical_routes' =>
                $missingCritical,

            'security_mismatches' =>
                $securityMismatches,

            'protected_without_identity_guards' =>
                $protectedWithoutIdentityGuard,
        ];
    }

    public function openApi(): array
    {
        $paths = [];

        foreach (
            $this->catalog()
            as $route
        ) {
            $path = $this
                ->openApiPath(
                    $route['uri']
                );

            $method = strtolower(
                $route['method']
            );

            $operation = [
                'operationId' =>
                    $route[
                        'operation_id'
                    ],

                'tags' => [
                    $route['tag'],
                ],

                'summary' =>
                    $this->summary(
                        $route['method'],
                        $path
                    ),

                'parameters' =>
                    $this->pathParameters(
                        $path
                    ),

                'responses' => [
                    '200' => [
                        'description' =>
                            'Successful response',
                    ],

                    '401' => [
                        '$ref' =>
                            '#/components/responses/Unauthenticated',
                    ],

                    '403' => [
                        '$ref' =>
                            '#/components/responses/Forbidden',
                    ],

                    '422' => [
                        '$ref' =>
                            '#/components/responses/ValidationError',
                    ],

                    '429' => [
                        '$ref' =>
                            '#/components/responses/RateLimited',
                    ],
                ],
            ];

            if ($path === '/app/bootstrap' && $route['method'] === 'GET') {
                $operation['summary'] = 'Bootstrap the authenticated resident application';
                $operation['responses']['200'] = [
                    'description' => 'Authenticated resident contexts',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/MobileBootstrapResponse'],
                        ],
                    ],
                ];
            }

            if (
                in_array($path, ['/auth/password/login', '/auth/otp/login'], true)
                && $route['method'] === 'POST'
            ) {
                $operation['responses']['200'] = [
                    'description' => 'Authentication successful',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/LoginResponse'],
                        ],
                    ],
                ];
                $operation['responses']['403'] = [
                    'description' => 'Account not allowed or identity verification required',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                            'examples' => [
                                'accountNotAllowed' => ['value' => [
                                    'code' => 'AUTH_ACCOUNT_NOT_ALLOWED',
                                    'message' => 'This account is not allowed to sign in.',
                                ]],
                                'identityVerificationRequired' => ['value' => [
                                    'code' => 'IDENTITY_VERIFICATION_REQUIRED',
                                    'message' => 'A verified mobile number or email address is required.',
                                ]],
                            ],
                        ],
                    ],
                ];
            }

            if ($path === '/auth/password/login' && $route['method'] === 'POST') {
                $operation['responses']['401'] = [
                    'description' => 'Credentials are invalid',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                            'example' => [
                                'code' => 'AUTH_INVALID_CREDENTIALS',
                                'message' => 'The provided credentials are invalid.',
                            ],
                        ],
                    ],
                ];
            }

            if ($route['protected']) {
                $operation['security'] = [
                    [
                        'bearerAuth' => [],
                    ],
                ];
            }

            if ($this->isMultipartUploadRoute($route)) {
                $operation['requestBody'] = [
                    'required' => true,
                    'content' => [
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['file'],
                                'properties' => [
                                    'file' => [
                                        'type' => 'string',
                                        'format' => 'binary',
                                    ],
                                    'category' => [
                                        'type' => 'string',
                                    ],
                                    'purpose' => [
                                        'type' => 'string',
                                        'nullable' => true,
                                    ],
                                    'is_confidential' => [
                                        'type' => 'boolean',
                                    ],
                                    'expires_at' => [
                                        'type' => 'string',
                                        'format' => 'date-time',
                                        'nullable' => true,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];
            } elseif (
                in_array(
                    $route['method'],
                    [
                        'POST',
                        'PUT',
                        'PATCH',
                    ],
                    true
                )
                && is_array(
                    $route[
                        'request_example'
                    ]
                )
            ) {
                $operation[
                    'requestBody'
                ] = [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' =>
                                    'object',
                                'additionalProperties' =>
                                    true,
                            ],
                            'example' =>
                                $route[
                                    'request_example'
                                ],
                        ],
                    ],
                ];
            }

            $paths[$path][$method] =
                $operation;
        }

        ksort($paths);

        return [
            'openapi' => '3.0.3',

            'info' => [
                'title' =>
                    'Buildino API',
                'version' =>
                    config(
                        'api_contract_v1.version',
                        '1.0.0'
                    ),
                'description' =>
                    'Runtime-derived API V1 contract for Buildino.',
            ],

            'servers' => [
                [
                    'url' =>
                        rtrim(
                            (string) config(
                                'app.url',
                                'http://127.0.0.1:8000'
                            ),
                            '/'
                        )
                        .'/api/v1',
                ],
            ],

            'paths' => $paths,

            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' =>
                            'Sanctum',
                    ],
                ],

                'responses' => [
                    'Unauthenticated' => [
                        'description' =>
                            'Authentication required',
                        'content' => ['application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                            'example' => [
                                'code' => 'UNAUTHENTICATED',
                                'message' => 'Authentication is required.',
                            ],
                        ]],
                    ],

                    'Forbidden' => [
                        'description' =>
                            'Permission denied, account not allowed, or identity verification required',
                        'content' => ['application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                        ]],
                    ],

                    'ValidationError' => [
                        'description' =>
                            'Request validation failed',
                        'content' => ['application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ValidationErrorResponse'],
                        ]],
                    ],

                    'RateLimited' => [
                        'description' => 'Request rate limit exceeded',
                        'content' => ['application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                            'example' => [
                                'code' => 'RATE_LIMITED',
                                'message' => 'Too many requests.',
                            ],
                        ]],
                    ],
                ],

                'schemas' => $this->contractSchemas(),
            ],
        ];
    }

    private function contractSchemas(): array
    {
        return [
            'ErrorResponse' => [
                'type' => 'object',
                'required' => ['code', 'message'],
                'properties' => [
                    'code' => ['type' => 'string'],
                    'message' => ['type' => 'string'],
                ],
            ],
            'ValidationErrorResponse' => [
                'allOf' => [
                    ['$ref' => '#/components/schemas/ErrorResponse'],
                    [
                        'type' => 'object',
                        'required' => ['errors'],
                        'properties' => [
                            'errors' => [
                                'type' => 'object',
                                'additionalProperties' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'LoginResponse' => [
                'type' => 'object',
                'required' => ['data', 'access_token', 'token_type'],
                'properties' => [
                    'data' => ['type' => 'object', 'additionalProperties' => true],
                    'access_token' => ['type' => 'string'],
                    'token_type' => ['type' => 'string', 'enum' => ['Bearer']],
                ],
            ],
            'MobileBootstrapResponse' => [
                'type' => 'object',
                'required' => ['data'],
                'properties' => [
                    'data' => [
                        'type' => 'object',
                        'required' => ['user', 'personas', 'contexts', 'suggested_context'],
                        'properties' => [
                            'user' => ['type' => 'object', 'additionalProperties' => true],
                            'personas' => [
                                'type' => 'array',
                                'items' => ['type' => 'string', 'enum' => ['owner', 'occupant']],
                            ],
                            'contexts' => [
                                'type' => 'array',
                                'items' => ['$ref' => '#/components/schemas/ResidentContext'],
                            ],
                            'suggested_context' => [
                                'type' => 'string',
                                'nullable' => true,
                                'description' => 'A deterministic ID from contexts, or null when contexts is empty.',
                            ],
                        ],
                    ],
                ],
            ],
            'ResidentContext' => [
                'type' => 'object',
                'required' => ['id', 'building', 'unit', 'relationships', 'capabilities'],
                'properties' => [
                    'id' => ['type' => 'string', 'example' => 'unit-42'],
                    'building' => ['type' => 'object', 'additionalProperties' => true],
                    'unit' => ['type' => 'object', 'additionalProperties' => true],
                    'relationships' => [
                        'type' => 'object',
                        'required' => ['owner', 'occupant'],
                        'properties' => [
                            'owner' => ['type' => 'boolean'],
                            'occupant' => ['type' => 'boolean'],
                        ],
                    ],
                    'capabilities' => [
                        'type' => 'object',
                        'description' => 'Context-local capabilities. charges.view permits viewing unit charges; wallet.view permits viewing the unit wallet.',
                        'required' => ['charges.view', 'wallet.view'],
                        'properties' => [
                            'charges.view' => ['type' => 'boolean'],
                            'wallet.view' => ['type' => 'boolean'],
                        ],
                        'additionalProperties' => ['type' => 'boolean'],
                    ],
                ],
            ],
        ];
    }

    public function postmanCollection(): array
    {
        $folders = [];

        foreach (
            $this->catalog()
            as $route
        ) {
            $tag =
                $route['tag'];

            $folders[$tag] ??= [
                'name' => $tag,
                'item' => [],
            ];

            $folders[$tag]['item'][] =
                $this->postmanItem(
                    $route
                );
        }

        ksort($folders);

        return [
            'info' => [
                '_postman_id' =>
                    (string) Str::uuid(),
                'name' =>
                    'Buildino API V1',
                'description' =>
                    'Runtime-aligned Buildino API V1 collection. Generated from Laravel routes.',
                'schema' =>
                    'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],

            'auth' => [
                'type' => 'bearer',
                'bearer' => [
                    [
                        'key' => 'token',
                        'value' =>
                            '{{access_token}}',
                        'type' => 'string',
                    ],
                ],
            ],

            'variable' => [
                [
                    'key' => 'base_url',
                    'value' =>
                        'http://127.0.0.1:8000',
                ],
            ],

            'item' =>
                array_values(
                    $folders
                ),
        ];
    }

    public function postmanEnvironment(): array
    {
        $variables = [
            'base_url' =>
                'http://127.0.0.1:8000',
            'access_token' => '',
            'mobile' => '',
            'password' => '',
            'otp_code' => '',
            'gateway' => 'fake',
            'payment_idempotency_key' =>
                'postman-payment-idempotency',
            'building_id' => '',
            'complex_id' => '',
            'block_id' => '',
            'floor_id' => '',
            'unit_id' => '',
            'facility_id' => '',
            'facility_reservation_id' => '',
            'wallet_id' => '',
            'wallet_topup_id' => '',
            'payment_id' => '',
            'service_request_id' => '',
            'service_quote_id' => '',
            'document_id' => '',
            'meeting_minute_id' => '',
            'file_uuid' => '',
            'report_definition_id' => '',
            'generated_report_id' => '',
            'provider_bank_account_id' => '',
            'provider_payout_id' => '',
            'wallet_transfer_id' => '',
        ];

        return [
            'id' =>
                (string) Str::uuid(),
            'name' =>
                'Buildino Local',
            'values' => array_map(
                static fn (
                    string $key,
                    string $value
                ): array => [
                    'key' => $key,
                    'value' => $value,
                    'enabled' => true,
                ],
                array_keys($variables),
                array_values($variables)
            ),
            '_postman_variable_scope' =>
                'environment',
            '_postman_exported_using' =>
                'Buildino ApiContractService',
        ];
    }

    public function manifest(): array
    {
        $audit = $this->audit();

        return [
            'version' =>
                $audit['version'],
            'generated_at' =>
                now()->toISOString(),
            'route_count' =>
                $audit['route_count'],
            'contract_hash' =>
                $audit['contract_hash'],
            'routes' => array_map(
                static fn (
                    array $route
                ): array => [
                    'method' =>
                        $route['method'],
                    'uri' =>
                        $route['uri'],
                    'protected' =>
                        $route['protected'],
                    'operation_id' =>
                        $route[
                            'operation_id'
                        ],
                    'tag' =>
                        $route['tag'],
                ],
                $this->catalog()
            ),
        ];
    }

    private function postmanItem(
        array $route
    ): array {
        $rawUrl =
            '{{base_url}}/'
            .$this->postmanUri(
                $route['uri']
            );

        $headers = [
            [
                'key' => 'Accept',
                'value' =>
                    'application/json',
            ],
        ];

        $request = [
            'method' =>
                $route['method'],
            'header' => $headers,
            'url' => [
                'raw' => $rawUrl,
                'host' => [
                    '{{base_url}}',
                ],
                'path' => array_values(
                    array_filter(
                        explode(
                            '/',
                            $this->postmanUri(
                                $route['uri']
                            )
                        )
                    )
                ),
            ],
        ];

        if (! $route['protected']) {
            $request['auth'] = [
                'type' => 'noauth',
            ];
        }

        if ($this->isMultipartUploadRoute($route)) {
            $request['body'] = [
                'mode' => 'formdata',
                'formdata' => [
                    [
                        'key' => 'file',
                        'type' => 'file',
                        'src' => [],
                    ],
                    [
                        'key' => 'category',
                        'value' => 'other',
                        'type' => 'text',
                    ],
                    [
                        'key' => 'purpose',
                        'value' => 'primary',
                        'type' => 'text',
                    ],
                    [
                        'key' => 'is_confidential',
                        'value' => 'false',
                        'type' => 'text',
                    ],
                ],
            ];
        }

        if (
            ! $this->isMultipartUploadRoute($route)
            &&
            is_array(
                $route['request_example']
            )
            && in_array(
                $route['method'],
                [
                    'POST',
                    'PUT',
                    'PATCH',
                ],
                true
            )
        ) {
            $request['header'][] = [
                'key' =>
                    'Content-Type',
                'value' =>
                    'application/json',
            ];

            $request['body'] = [
                'mode' => 'raw',
                'raw' =>
                    json_encode(
                        $route[
                            'request_example'
                        ],
                        JSON_PRETTY_PRINT
                        | JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                        | JSON_THROW_ON_ERROR
                    ),
                'options' => [
                    'raw' => [
                        'language' =>
                            'json',
                    ],
                ],
            ];
        }

        $events = [
            [
                'listen' => 'test',
                'script' => [
                    'type' =>
                        'text/javascript',
                    'exec' => [
                        'pm.test("No server error", function () {',
                        '  pm.expect(pm.response.code).to.be.below(500);',
                        '});',
                        'pm.test("Request ID is present", function () {',
                        '  pm.expect(pm.response.headers.get("X-Request-ID")).to.exist;',
                        '});',
                    ],
                ],
            ],
        ];

        $capture = $this->captureScript(
            $route['method']
            .' '
            .$route['uri']
        );

        if ($capture !== []) {
            $events[0]['script']['exec'] =
                array_merge(
                    $events[0]['script']['exec'],
                    $capture
                );
        }

        return [
            'name' =>
                $route['method']
                .' '
                .$this->openApiPath(
                    $route['uri']
                ),
            'request' =>
                $request,
            'event' =>
                $events,
        ];
    }

    private function captureScript(
        string $key
    ): array {
        return match ($key) {
            'POST api/v1/auth/password/login',
            'POST api/v1/auth/otp/login' => [
                'if (pm.response.code === 200) {',
                '  const json = pm.response.json();',
                '  if (json.access_token) pm.environment.set("access_token", json.access_token);',
                '}',
            ],

            'GET api/v1/wallets/me' => [
                'if (pm.response.code === 200) {',
                '  const json = pm.response.json();',
                '  if (json.data?.id) pm.environment.set("wallet_id", json.data.id);',
                '}',
            ],

            'POST api/v1/buildings/{building}/wallet-topups' => [
                'if (pm.response.code === 201) {',
                '  const json = pm.response.json();',
                '  if (json.data?.id) pm.environment.set("wallet_topup_id", json.data.id);',
                '  if (json.data?.payment_id) pm.environment.set("payment_id", json.data.payment_id);',
                '}',
            ],

            'POST api/v1/payments/{payment}/gateway/initiate' => [
                'if (pm.response.code >= 200 && pm.response.code < 300) {',
                '  const json = pm.response.json();',
                '  if (json.data?.authority) pm.environment.set("gateway_authority", json.data.authority);',
                '}',
            ],

            'POST api/v1/facilities/{buildingFacility}/reservations' => [
                'if (pm.response.code >= 200 && pm.response.code < 300) {',
                '  const json = pm.response.json();',
                '  if (json.data?.id) pm.environment.set("facility_reservation_id", json.data.id);',
                '}',
            ],

            'POST api/v1/service-requests/{serviceRequest}/quotes' => [
                'if (pm.response.code >= 200 && pm.response.code < 300) {',
                '  const json = pm.response.json();',
                '  if (json.data?.id) pm.environment.set("service_quote_id", json.data.id);',
                '}',
            ],

            'POST api/v1/provider/bank-accounts' => [
                'if (pm.response.code === 201) {',
                '  const json = pm.response.json();',
                '  if (json.data?.id) pm.environment.set("provider_bank_account_id", json.data.id);',
                '}',
            ],

            'POST api/v1/provider/payouts' => [
                'if (pm.response.code === 201) {',
                '  const json = pm.response.json();',
                '  if (json.data?.id) pm.environment.set("provider_payout_id", json.data.id);',
                '}',
            ],

            'POST api/v1/report-definitions/{reportDefinition}/exports' => [
                'if (pm.response.code === 202) {',
                '  const json = pm.response.json();',
                '  if (json.data?.id) pm.environment.set("generated_report_id", json.data.id);',
                '}',
            ],

            'POST api/v1/documents/{document}/files',
            'POST api/v1/meeting-minutes/{meetingMinute}/files' => [
                'if (pm.response.code === 201) {',
                '  const json = pm.response.json();',
                '  if (json.data?.uuid) pm.environment.set("file_uuid", json.data.uuid);',
                '}',
            ],

            default => [],
        };
    }

    private function postmanUri(
        string $uri
    ): string {
        return preg_replace_callback(
            '/\{([^}]+)\}/',
            static function (
                array $matches
            ): string {
                $parameter =
                    $matches[1];

                $variable = config(
                    'api_contract_v1.postman_parameter_variables.'
                    .$parameter,
                    Str::snake(
                        $parameter
                    ).'_id'
                );

                return '{{'
                    .$variable
                    .'}}';
            },
            $uri
        );
    }

    private function openApiPath(
        string $uri
    ): string {
        $uri = preg_replace(
            '#^api/v1#',
            '',
            $uri
        );

        return $uri === ''
            ? '/'
            : '/'.ltrim(
                $uri,
                '/'
            );
    }

    private function pathParameters(
        string $path
    ): array {
        preg_match_all(
            '/\{([^}]+)\}/',
            $path,
            $matches
        );

        return array_map(
            fn (string $name): array => [
                'name' => $name,
                'in' => 'path',
                'required' => true,
                'schema' => match ($name) {
                    'gateway' => [
                        'type' => 'string',
                    ],
                    'file' => [
                        'type' => 'string',
                        'format' => 'uuid',
                    ],
                    default => [
                        'type' => 'integer',
                    ],
                },
            ],
            $matches[1]
        );
    }

    private function isMultipartUploadRoute(array $route): bool
    {
        return $route['method'] === 'POST'
            && in_array(
                $route['uri'],
                [
                    'api/v1/documents/{document}/files',
                    'api/v1/meeting-minutes/{meetingMinute}/files',
                ],
                true
            );
    }

    private function isProtected(
        array $middleware
    ): bool {
        return $this->hasMiddleware(
            $middleware,
            [
                'auth:sanctum',
                \Illuminate\Auth\Middleware\Authenticate::class,
            ]
        );
    }

    private function hasMiddleware(
        array $middleware,
        array $needles
    ): bool {
        foreach ($middleware as $item) {
            $item = $this->normalizeMiddleware(
                (string) $item
            );

            foreach ($needles as $needle) {
                $needle = $this->normalizeMiddleware(
                    (string) $needle
                );

                if (
                    $item === $needle
                    || str_starts_with(
                        $item,
                        $needle.':'
                    )
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeMiddleware(
        string $middleware
    ): string {
        return ltrim(
            trim($middleware),
            '\\'
        );
    }

    private function routeKey(
        string $method,
        string $uri
    ): string {
        return strtoupper(
            trim($method)
        )
        .' '
        .$this->normalizeUri(
            $uri
        );
    }

    private function normalizeRouteKey(
        string $key
    ): string {
        $parts = preg_split(
            '/\s+/',
            trim($key),
            2
        );

        if (
            ! is_array($parts)
            || count($parts) !== 2
        ) {
            return trim($key);
        }

        return $this->routeKey(
            $parts[0],
            $parts[1]
        );
    }

    private function normalizeUri(
        string $uri
    ): string {
        $uri = preg_replace(
            '#/+#',
            '/',
            ltrim(
                trim($uri),
                '/'
            )
        ) ?? $uri;

        return rtrim(
            $uri,
            '/'
        );
    }

    private function httpMethods(
        Route $route
    ): array {
        return array_values(
            array_filter(
                array_map(
                    'strtoupper',
                    $route->methods()
                ),
                static fn (
                    string $method
                ): bool =>
                    ! in_array(
                        $method,
                        [
                            'HEAD',
                            'OPTIONS',
                        ],
                        true
                    )
            )
        );
    }

    private function operationId(
        string $method,
        string $uri
    ): string {
        $value = strtolower(
            $method
            .'_'
            .preg_replace(
                '/[^A-Za-z0-9]+/',
                '_',
                $uri
            )
        );

        return trim(
            $value,
            '_'
        );
    }

    private function summary(
        string $method,
        string $path
    ): string {
        return ucfirst(
            strtolower($method)
        )
        .' '
        .$path;
    }

    private function tagFor(
        string $uri
    ): string {
        $path = preg_replace(
            '#^api/v1/#',
            '',
            $uri
        );

        return match (true) {
            str_starts_with(
                $path,
                'auth/'
            ) =>
                'Authentication',

            str_starts_with(
                $path,
                'payment-gateways/'
            ),
            str_starts_with(
                $path,
                'payments/'
            ) =>
                'Payments',

            str_contains(
                $path,
                'wallet'
            ) =>
                'Wallets',

            str_contains(
                $path,
                'reports'
            ),
            str_starts_with(
                $path,
                'report-'
            ) =>
                'Reports',

            str_contains(
                $path,
                'facility'
            ) =>
                'Facilities',

            str_contains(
                $path,
                'guest'
            ) =>
                'Guests',

            str_contains(
                $path,
                'service'
            ) =>
                'Services',

            str_contains(
                $path,
                'invoice'
            ),
            str_contains(
                $path,
                'charge'
            ) =>
                'Charges & Invoices',

            str_contains(
                $path,
                'financial'
            ),
            str_contains(
                $path,
                'expense'
            ),
            str_contains(
                $path,
                'income'
            ) =>
                'Accounting',

            str_contains(
                $path,
                'provider'
            ) =>
                'Providers',

            str_contains(
                $path,
                'support'
            ) =>
                'Support',

            str_contains(
                $path,
                'files'
            ),
            str_contains(
                $path,
                'documents'
            ),
            str_contains(
                $path,
                'meeting-minutes'
            ) =>
                'Documents & Files',

            str_contains(
                $path,
                'system/'
            ),
            str_starts_with(
                $path,
                'admin/system'
            ) =>
                'System',

            str_contains(
                $path,
                'unit'
            ),
            str_contains(
                $path,
                'floor'
            ),
            str_contains(
                $path,
                'block'
            ) =>
                'Building Structure',

            str_contains(
                $path,
                'building'
            ),
            str_contains(
                $path,
                'complex'
            ) =>
                'Buildings',

            default =>
                'General',
        };
    }
}
