<?php

namespace Tests\Feature\System;

use App\Enums\FinancialAccountType;
use App\Enums\FinancialTransactionType;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Services\System\FinalIntegrityAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class BackendIntegrityAuditTest extends TestCase
{
    use RefreshDatabase, CreatesBuildingDomainData;

    public function test_integrity_audit_detects_unbalanced_ledger(): void
    {
        $graph = $this->createBuildingGraph();

        $account = FinancialAccount::query()->create([
            'building_id' => $graph['building']->id,
            'code' => 'AUDIT-CASH',
            'title' => 'Audit cash',
            'type' => FinancialAccountType::Cash->value,
            'currency' => 'IRR',
            'is_active' => true,
        ]);

        $transaction = FinancialTransaction::query()->create([
            'uuid' => (string) str()->uuid(),
            'building_id' => $graph['building']->id,
            'transaction_type' =>
                FinancialTransactionType::Income->value,
            'occurred_at' => now(),
        ]);

        DB::table('financial_ledger_entries')->insert([
            'financial_transaction_id' =>
                $transaction->id,
            'financial_account_id' =>
                $account->id,
            'entry_type' => 'debit',
            'amount' => 1000,
            'currency' => 'IRR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $audit = app(
            FinalIntegrityAuditService::class
        )->inspect();

        $this->assertFalse($audit['ok']);

        $checks = collect($audit['checks'])
            ->keyBy('name');

        $this->assertSame(
            1,
            (int) $checks
                ->get('financial_ledger_unbalanced')['count']
        );
    }
}
