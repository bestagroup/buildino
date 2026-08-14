<?php

namespace Tests\Feature\System;

use App\Models\Building;
use App\Models\FacilityReservation;
use App\Models\NotificationLog;
use App\Models\ServiceRequest;
use App\Models\SupportTicket;
use App\Models\Unit;
use App\Models\UnitInvoice;
use App\Models\User;
use App\Models\Wallet;
use App\Services\System\FinalIntegrityAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DemoDataGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_tiny_demo_dataset_is_relational_and_passes_critical_integrity_checks(): void
    {
        config()->set('demo_data.scales.tiny', [
            'complexes' => 1,
            'buildings_per_complex' => 1,
            'blocks_per_building' => 1,
            'floors_per_block' => 1,
            'units_per_floor' => 2,
            'invoice_months' => 1,
            'providers_per_building' => 1,
            'reservations_per_building' => 3,
            'services_per_building' => 2,
            'tickets_per_building' => 2,
            'guest_visits_per_building' => 3,
            'notifications_per_resident' => 1,
        ]);

        $exit = Artisan::call('buildino:demo-data', [
            '--scale' => 'tiny',
            '--seed' => 1405,
            '--batch' => 'test-demo',
        ]);

        $this->assertSame(0, $exit, Artisan::output());

        $this->assertSame(1, Building::query()->count());
        $this->assertSame(2, Unit::query()->count());
        $this->assertGreaterThanOrEqual(5, User::query()->count());
        $this->assertGreaterThanOrEqual(4, Wallet::query()->count());
        $this->assertSame(2, UnitInvoice::query()->count());
        $this->assertSame(3, FacilityReservation::query()->count());
        $this->assertSame(2, ServiceRequest::query()->count());
        $this->assertSame(2, SupportTicket::query()->count());
        $this->assertGreaterThan(0, NotificationLog::query()->count());

        $audit = app(FinalIntegrityAuditService::class)->inspect();

        $this->assertTrue(
            $audit['ok'],
            json_encode(
                $audit,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            )
        );
    }

    public function test_demo_command_dry_run_does_not_write_data(): void
    {
        $exit = Artisan::call('buildino:demo-data', [
            '--scale' => 'small',
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exit);
        $this->assertSame(0, Building::query()->count());
        $this->assertSame(0, Unit::query()->count());
    }
}
