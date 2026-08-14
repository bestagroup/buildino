<?php

namespace Database\Seeders;

use App\Services\Demo\DemoDataGenerator;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing', 'staging'])) {
            $this->command?->error(
                'DemoDataSeeder is restricted to local/testing/staging environments.'
            );

            return;
        }

        $this->call([
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
        ]);

        $scale = (string) config('demo_data.default_scale', 'medium');

        $summary = app(DemoDataGenerator::class)->generate(
            $scale,
            null,
            null,
            fn (string $message) => $this->command?->line($message)
        );

        $this->command?->newLine();
        $this->command?->info('Buildino demo data created successfully.');
        $this->command?->line('Batch: '.$summary['batch']);
        $this->command?->line('Scale: '.$summary['scale']);
        $this->command?->line('Demo manager: '.$summary['credentials']['manager']);
        $this->command?->line('Demo resident: '.$summary['credentials']['resident']);
        $this->command?->line('Demo provider: '.$summary['credentials']['provider']);
        $this->command?->line('Demo password: '.$summary['credentials']['password']);
    }
}
