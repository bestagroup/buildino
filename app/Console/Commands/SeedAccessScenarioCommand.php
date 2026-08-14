<?php

namespace App\Console\Commands;

use Database\Seeders\AccessScenarioSeeder;
use Illuminate\Console\Command;

class SeedAccessScenarioCommand extends Command
{
    protected $signature =
        'buildino:access-scenario';

    protected $description =
        'Seed Buildino role matrix and deterministic access test scenario';

    public function handle(): int
    {
        if (
            ! app()->environment([
                'local',
                'testing',
            ])
        ) {
            $this->error(
                'This command is available only in local/testing environments.'
            );

            return self::FAILURE;
        }

        $this->call(
            'db:seed',
            [
                '--class' =>
                    AccessScenarioSeeder::class,

                '--force' =>
                    true,
            ]
        );

        return self::SUCCESS;
    }
}
