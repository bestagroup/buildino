<?php

namespace App\Console\Commands;

use App\Services\ApiContract\ApiContractService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportApiContract extends Command
{
    protected $signature =
        'api:contract:export
        {--output=docs/api : OpenAPI/manifest output directory relative to project root}
        {--postman=postman : Postman output directory relative to project root}';

    protected $description =
        'Export runtime Buildino V1 OpenAPI, route manifest and Postman files';

    public function handle(
        ApiContractService $contracts
    ): int {
        $audit =
            $contracts->audit();

        if (! $audit['ok']) {
            $this->error(
                'Contract audit failed. Export aborted.'
            );

            $this->call(
                'api:contract:audit'
            );

            return self::FAILURE;
        }

        $output = base_path(
            trim(
                (string) $this->option(
                    'output'
                ),
                '/'
            )
        );

        $postman = base_path(
            trim(
                (string) $this->option(
                    'postman'
                ),
                '/'
            )
        );

        File::ensureDirectoryExists(
            $output
        );

        File::ensureDirectoryExists(
            $postman
        );

        $openApi =
            $contracts->openApi();

        $manifest =
            $contracts->manifest();

        $collection =
            $contracts
                ->postmanCollection();

        $environment =
            $contracts
                ->postmanEnvironment();

        $this->writeJson(
            $output
                .'/openapi-v1.json',
            $openApi
        );

        /*
         * JSON syntax is a valid YAML 1.2 document. Keeping the exact same
         * serialized document in .yaml avoids introducing a YAML dependency
         * while retaining compatibility with OpenAPI tooling that accepts
         * YAML 1.2.
         */
        $this->writeJson(
            $output
                .'/openapi-v1.yaml',
            $openApi
        );

        $this->writeJson(
            $output
                .'/route-manifest-v1.json',
            $manifest
        );

        $this->writeJson(
            $postman
                .'/Buildino_API_v1.postman_collection.json',
            $collection
        );

        $this->writeJson(
            $postman
                .'/Buildino_Local.postman_environment.json',
            $environment
        );

        $this->table(
            [
                'Artifact',
                'Path',
            ],
            [
                [
                    'OpenAPI JSON',
                    $output
                        .'/openapi-v1.json',
                ],
                [
                    'OpenAPI YAML-compatible',
                    $output
                        .'/openapi-v1.yaml',
                ],
                [
                    'Route manifest',
                    $output
                        .'/route-manifest-v1.json',
                ],
                [
                    'Postman collection',
                    $postman
                        .'/Buildino_API_v1.postman_collection.json',
                ],
                [
                    'Postman environment',
                    $postman
                        .'/Buildino_Local.postman_environment.json',
                ],
            ]
        );

        $this->info(
            'API V1 contract artifacts exported.'
        );

        return self::SUCCESS;
    }

    private function writeJson(
        string $path,
        array $data
    ): void {
        File::put(
            $path,
            json_encode(
                $data,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            )
            .PHP_EOL
        );
    }
}
