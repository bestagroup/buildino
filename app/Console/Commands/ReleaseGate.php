<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ReleaseGate extends Command
{
    protected $signature = 'release:gate
        {--production : Include strict production runtime/config checks}';

    protected $description =
        'Run Buildino non-test release gates before final documentation/freeze';

    public function handle(): int
    {
        $commands = [
            ['api:contract:audit', []],
            ['system:integrity-audit', []],
            ['wallet-accounting:audit', []],
            ['payments:gateway-audit', []],
        ];

        if ($this->option('production')) {
            $commands[] = [
                'system:production-audit',
                ['--strict' => true],
            ];

            $commands[] = [
                'system:health',
                ['--fail-on-degraded' => true],
            ];
        }

        $failed = [];

        foreach ($commands as [$command, $arguments]) {
            $this->newLine();
            $this->info('Running '.$command);

            $exitCode = $this->call(
                $command,
                $arguments
            );

            if ($exitCode !== self::SUCCESS) {
                $failed[] = $command;
            }
        }

        if ($failed !== []) {
            $this->error(
                'Release gate failed: '.implode(', ', $failed)
            );

            return self::FAILURE;
        }

        $this->info('All Buildino release gates passed.');
        $this->comment(
            'Run the full PHPUnit suite separately: php artisan test'
        );

        return self::SUCCESS;
    }
}
