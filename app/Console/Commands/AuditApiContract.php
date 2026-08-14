<?php

namespace App\Console\Commands;

use App\Services\ApiContract\ApiContractService;
use Illuminate\Console\Command;

class AuditApiContract extends Command
{
    protected $signature =
        'api:contract:audit
        {--json : Output machine-readable JSON}';

    protected $description =
        'Audit Buildino API V1 route/security contract';

    public function handle(
        ApiContractService $contracts
    ): int {
        $result =
            $contracts->audit();

        if ($this->option('json')) {
            $this->line(
                json_encode(
                    $result,
                    JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
                )
            );

            return $result['ok']
                ? self::SUCCESS
                : self::FAILURE;
        }

        $this->table(
            [
                'Metric',
                'Value',
            ],
            [
                [
                    'API version',
                    $result['version'],
                ],
                [
                    'Route-method contracts',
                    $result['route_count'],
                ],
                [
                    'Contract SHA-256',
                    $result['contract_hash'],
                ],
                [
                    'Duplicate contracts',
                    count(
                        $result['duplicates']
                    ),
                ],
                [
                    'Unexpected public routes',
                    count(
                        $result[
                            'unexpected_public_routes'
                        ]
                    ),
                ],
                [
                    'Missing allowed public routes',
                    count(
                        $result[
                            'missing_allowed_public_routes'
                        ]
                    ),
                ],
                [
                    'Missing critical routes',
                    count(
                        $result[
                            'missing_critical_routes'
                        ]
                    ),
                ],
                [
                    'Security mismatches',
                    count(
                        $result[
                            'security_mismatches'
                        ]
                    ),
                ],
                [
                    'Protected routes missing identity guards',
                    count(
                        $result[
                            'protected_without_identity_guards'
                        ]
                    ),
                ],
            ]
        );

        foreach (
            [
                'duplicates' =>
                    'Duplicate routes',

                'unexpected_public_routes' =>
                    'Unexpected PUBLIC routes',

                'missing_allowed_public_routes' =>
                    'Missing expected public routes',

                'missing_critical_routes' =>
                    'Missing critical routes',

                'protected_without_identity_guards' =>
                    'Protected routes missing guards',
            ]
            as $key => $title
        ) {
            $items = $result[$key];

            if ($items === []) {
                continue;
            }

            $this->newLine();

            $this->warn($title);

            foreach ($items as $item) {
                $this->line(
                    '- '
                    .(
                        is_array($item)
                            ? json_encode(
                                $item,
                                JSON_UNESCAPED_SLASHES
                                | JSON_UNESCAPED_UNICODE
                            )
                            : $item
                    )
                );
            }
        }

        if (
            $result[
                'security_mismatches'
            ] !== []
        ) {
            $this->newLine();

            $this->warn(
                'Critical route security mismatches'
            );

            foreach (
                $result[
                    'security_mismatches'
                ]
                as $item
            ) {
                $this->line(
                    '- '
                    .json_encode(
                        $item,
                        JSON_UNESCAPED_SLASHES
                    )
                );
            }
        }

        if ($result['ok']) {
            $this->info(
                'API V1 contract audit passed.'
            );

            return self::SUCCESS;
        }

        $this->error(
            'API V1 contract audit failed.'
        );

        return self::FAILURE;
    }
}
