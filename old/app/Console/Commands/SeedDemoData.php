<?php

namespace App\Console\Commands;

use App\Services\Demo\DemoDataGenerator;
use Database\Seeders\FinalCompletionPermissionSeeder;
use Database\Seeders\PaymentGatewayPermissionSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ProviderSettlementPermissionSeeder;
use Database\Seeders\ReportExportPermissionSeeder;
use Database\Seeders\ReportingCatalogSeeder;
use Database\Seeders\ReportingPermissionSeeder;
use Database\Seeders\ServiceMarketplacePermissionSeeder;
use Database\Seeders\SystemHealthPermissionSeeder;
use Database\Seeders\WalletAccountingPermissionSeeder;
use Database\Seeders\WalletOperationsPermissionSeeder;
use Illuminate\Console\Command;

class SeedDemoData extends Command
{
    protected $signature = 'buildino:demo-data
        {--scale=medium : small, medium or large}
        {--seed= : Optional deterministic random seed}
        {--batch= : Optional custom batch identifier}
        {--dry-run : Show estimated volume without writing data}';

    protected $description =
        'Generate relational, finance-aware demo data for Buildino dashboards and load testing';

    public function handle(
        DemoDataGenerator $generator
    ): int {
        if (! app()->environment(['local', 'testing', 'staging'])) {
            $this->error(
                'This command is disabled outside local/testing/staging environments.'
            );

            return self::FAILURE;
        }

        $scale = strtolower(
            trim((string) $this->option('scale'))
        );

        try {
            $estimate = $generator->estimate($scale);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Buildino Demo Data');
        $this->table(
            ['Item', 'Estimated count'],
            [
                ['Complexes', $estimate['complexes']],
                ['Buildings', $estimate['buildings']],
                ['Units', $estimate['units']],
                ['Users', '~'.$estimate['approx_users']],
                ['Invoices', $estimate['invoices']],
                ['Reservations', $estimate['reservations']],
                ['Service requests', $estimate['services']],
                ['Support tickets', $estimate['tickets']],
                ['Guest visits', $estimate['guest_visits']],
            ]
        );

        if ($this->option('dry-run')) {
            $this->comment('Dry-run only. No database rows were created.');

            return self::SUCCESS;
        }

        $this->seedCatalogs();

        $seedOption = $this->option('seed');
        $seed = $seedOption !== null && $seedOption !== ''
            ? (int) $seedOption
            : null;

        $batch = $this->option('batch');
        $batch = $batch !== null && trim((string) $batch) !== ''
            ? trim((string) $batch)
            : null;

        try {
            $summary = $generator->generate(
                $scale,
                $seed,
                $batch,
                fn (string $message) => $this->line($message)
            );
        } catch (\Throwable $exception) {
            $this->newLine();
            $this->error('Demo data generation failed.');
            $this->error($exception::class.': '.$exception->getMessage());

            if (app()->environment('local', 'testing')) {
                $this->line($exception->getTraceAsString());
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Demo data generation completed.');
        $this->line('Batch: '.$summary['batch']);
        $this->line('Scale: '.$summary['scale']);
        $this->newLine();

        $rows = [];

        foreach ($summary['counts'] as $key => $count) {
            $rows[] = [str_replace('_', ' ', $key), number_format((int) $count)];
        }

        $this->table(['Created', 'Count'], $rows);

        $this->newLine();
        $this->info('Ready-to-use demo credentials');
        $this->table(
            ['Account', 'Login'],
            [
                ['Scoped Manager', $summary['credentials']['manager']],
                ['Resident', $summary['credentials']['resident']],
                ['Service Provider', $summary['credentials']['provider']],
                ['Password', $summary['credentials']['password']],
            ]
        );

        $this->line('Management dashboard: /management/login');

        return self::SUCCESS;
    }

    private function seedCatalogs(): void
    {
        foreach ([
            PermissionSeeder::class,
            WalletOperationsPermissionSeeder::class,
            ServiceMarketplacePermissionSeeder::class,
            ProviderSettlementPermissionSeeder::class,
            WalletAccountingPermissionSeeder::class,
            ReportingPermissionSeeder::class,
            ReportExportPermissionSeeder::class,
            PaymentGatewayPermissionSeeder::class,
            SystemHealthPermissionSeeder::class,
            FinalCompletionPermissionSeeder::class,
            ReportingCatalogSeeder::class,
        ] as $seeder) {
            app($seeder)->run();
        }
    }
}
