<?php

namespace Tests\Feature\System;

use App\Models\ChargeCalculation;
use App\Models\ChargeFormula;
use App\Models\ChargePeriod;
use App\Models\StorageUnit;
use App\Models\UnitInvoice;
use App\Models\UnitOwnership;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class DatabaseHardeningTest extends TestCase
{
    use RefreshDatabase, CreatesBuildingDomainData;

    public function test_fractional_domain_values_are_not_truncated(): void
    {
        $graph = $this->createBuildingGraph();

        $graph['building']->update([
            'latitude' => 35.6891972,
            'longitude' => 51.3889736,
        ]);

        $graph['unit']->update([
            'area' => 123.45,
        ]);

        $storage = StorageUnit::query()->create([
            'building_id' => $graph['building']->getKey(),
            'storage_number' => 'S-'.Str::upper(Str::random(8)),
            'area' => 7.25,
            'is_active' => true,
        ]);

        $owner = $this->createUser();

        $ownership = UnitOwnership::query()->create([
            'unit_id' => $graph['unit']->getKey(),
            'user_id' => $owner->getKey(),
            'ownership_percentage' => 33.33,
            'starts_at' => now()->toDateString(),
            'is_primary' => true,
            'is_active' => true,
        ]);

        $formula = ChargeFormula::query()->create([
            'building_id' => $graph['building']->getKey(),
            'title' => 'Area precision',
            'calculation_type' => 'area',
            'configuration' => [],
            'is_active' => true,
        ]);

        $period = ChargePeriod::query()->create([
            'building_id' => $graph['building']->getKey(),
            'title' => 'Precision period',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
            'status' => 'draft',
        ]);

        $calculation = ChargeCalculation::query()->create([
            'charge_period_id' => $period->getKey(),
            'unit_id' => $graph['unit']->getKey(),
            'charge_formula_id' => $formula->getKey(),
            'base_value' => 123.4567,
            'calculated_amount' => 1000,
            'calculation_snapshot' => [],
        ]);

        $this->assertSame('35.6891972', $graph['building']->fresh()->latitude);
        $this->assertSame('51.3889736', $graph['building']->fresh()->longitude);
        $this->assertSame('123.45', $graph['unit']->fresh()->area);
        $this->assertSame('7.25', $storage->fresh()->area);
        $this->assertSame('33.33', $ownership->fresh()->ownership_percentage);
        $this->assertSame('123.4567', $calculation->fresh()->base_value);
    }

    public function test_a_payment_cannot_allocate_twice_to_the_same_payable(): void
    {
        $graph = $this->createBuildingGraph();
        $payer = $this->createUser();

        $invoiceId = DB::table('unit_invoices')->insertGetId([
            'building_id' => $graph['building']->getKey(),
            'unit_id' => $graph['unit']->getKey(),
            'invoice_number' => 'INV-'.Str::upper(Str::random(12)),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
            'subtotal' => 1000,
            'discount_amount' => 0,
            'penalty_amount' => 0,
            'waived_penalty_amount' => 0,
            'total_amount' => 1000,
            'paid_amount' => 0,
            'outstanding_amount' => 1000,
            'status' => 'issued',
            'payer_responsibility' => 'unit',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paymentId = DB::table('payments')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'building_id' => $graph['building']->getKey(),
            'payer_user_id' => $payer->getKey(),
            'payment_number' => 'PAY-'.Str::upper(Str::random(12)),
            'idempotency_key' => 'db-hardening-'.Str::uuid(),
            'amount' => 1000,
            'currency' => 'IRR',
            'method' => 'online',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = [
            'payment_id' => $paymentId,
            'payable_type' => (new UnitInvoice())->getMorphClass(),
            'payable_id' => $invoiceId,
            'amount' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('payment_allocations')->insert($row);

        $this->expectException(QueryException::class);

        DB::table('payment_allocations')->insert($row);
    }

    public function test_high_value_query_indexes_exist(): void
    {
        foreach ([
            ['otp_codes', 'otp_lookup_idx'],
            ['user_role_assignments', 'ura_user_active_window_idx'],
            ['unit_ownerships', 'uo_user_active_window_idx'],
            ['unit_occupancies', 'uoc_user_active_window_idx'],
            ['unit_invoices', 'ui_building_issue_status_idx'],
            ['invoice_installments', 'ii_invoice_status_due_idx'],
            ['service_requests', 'sr_building_created_idx'],
            ['facility_reservations', 'fr_booking_conflict_idx'],
        ] as [$table, $index]) {
            $this->assertTrue(
                Schema::hasIndex($table, $index),
                sprintf('Missing index [%s] on table [%s].', $index, $table)
            );
        }
    }
}
