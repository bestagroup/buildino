<?php

namespace Tests\Feature\Financial;

use App\Enums\InstallmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OccupancyType;
use App\Enums\PaymentMethod;
use App\Models\Building;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyRule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitInvoice;
use App\Models\UnitOccupancy;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\Loyalty\LoyaltyLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class InstallmentLoyaltyFlowTest extends TestCase
{
    use CreatesBuildingDomainData;
    use RefreshDatabase;

    public function test_installment_plan_is_balanced_audited_and_paid_in_due_order(): void
    {
        $graph = $this->createBuildingGraph();
        $manager = $this->createUser();
        $resident = $this->createUser();
        $this->grant(
            $manager,
            $graph['building'],
            ['invoices.update', 'payments.verify']
        );
        $this->occupy($resident, $graph['unit']);
        $invoice = $this->issuedInvoice(
            $graph['building'],
            $graph['unit'],
            1000
        );

        Sanctum::actingAs($manager);

        $this->putJson(
            "/api/v1/invoices/{$invoice->id}/installments",
            [
                'installments' => [
                    [
                        'due_date' => today()->addDays(10)->toDateString(),
                        'amount' => 400,
                    ],
                    [
                        'due_date' => today()->addDays(20)->toDateString(),
                        'amount' => 500,
                    ],
                ],
            ]
        )->assertUnprocessable();

        $this->putJson(
            "/api/v1/invoices/{$invoice->id}/installments",
            [
                'installments' => [
                    [
                        'due_date' => today()->addDays(20)->toDateString(),
                        'amount' => 600,
                    ],
                    [
                        'due_date' => today()->addDays(10)->toDateString(),
                        'amount' => 400,
                    ],
                ],
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.0.installment_number', 1)
            ->assertJsonPath('data.0.amount', 400)
            ->assertJsonPath('data.1.amount', 600);

        $this->assertDatabaseHas('financial_audit_logs', [
            'action' => 'invoice.installment_plan.replaced',
            'entity_id' => $invoice->id,
            'user_id' => $manager->id,
        ]);

        Sanctum::actingAs($resident);
        $paymentId = $this->postJson(
            "/api/v1/invoices/{$invoice->id}/payments",
            [
                'amount' => 500,
                'method' => PaymentMethod::Manual->value,
            ]
        )
            ->assertCreated()
            ->json('data.id');

        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/payments/{$paymentId}/verify")
            ->assertOk();
        $this->postJson("/api/v1/payments/{$paymentId}/verify")
            ->assertOk();

        $installments = $invoice->invoiceInstallments()
            ->orderBy('installment_number')
            ->get();

        $this->assertSame(400, (int) $installments[0]->paid_amount);
        $this->assertSame(InstallmentStatus::Paid, $installments[0]->status);
        $this->assertSame(100, (int) $installments[1]->paid_amount);
        $this->assertSame(InstallmentStatus::Partial, $installments[1]->status);

        $installments[1]->update([
            'due_date' => today()->subDay(),
        ]);

        $this->artisan('invoices:mark-overdue-installments')
            ->assertSuccessful();

        $this->assertSame(
            InstallmentStatus::Overdue,
            $installments[1]->refresh()->status
        );

        $this->deleteJson(
            "/api/v1/invoices/{$invoice->id}/installments"
        )->assertUnprocessable();
    }

    public function test_loyalty_ledger_is_idempotent_scoped_versioned_and_reversible(): void
    {
        $graph = $this->createBuildingGraph();
        $other = $this->createBuildingGraph();
        $manager = $this->createUser();
        $resident = $this->createUser();
        $this->grant(
            $manager,
            $graph['building'],
            [
                'loyalty-rewards.view',
                'loyalty-rewards.create',
                'loyalty-rewards.update',
                'payments.verify',
            ]
        );
        $this->occupy($resident, $graph['unit']);

        Sanctum::actingAs($manager);

        $firstRuleId = $this->postJson(
            "/api/v1/buildings/{$graph['building']->id}/loyalty-rules",
            [
                'event_type' => 'payment_verified',
                'points' => 2,
                'configuration' => [
                    'amount_step' => 100,
                    'maximum_points' => 20,
                ],
            ]
        )
            ->assertCreated()
            ->assertJsonPath('data.version', 1)
            ->json('data.id');

        $this->postJson(
            "/api/v1/buildings/{$graph['building']->id}/loyalty-rules",
            [
                'event_type' => 'payment_verified',
                'points' => 2,
                'configuration' => [
                    'amount_step' => 100,
                    'maximum_points' => 20,
                ],
            ]
        )
            ->assertCreated()
            ->assertJsonPath('data.version', 2);

        $this->assertFalse(
            (bool) LoyaltyRule::query()->findOrFail($firstRuleId)->is_active
        );

        $rewardId = $this->postJson(
            "/api/v1/buildings/{$graph['building']->id}/loyalty-rewards",
            [
                'title' => 'Free service',
                'required_points' => 4,
            ]
        )
            ->assertCreated()
            ->json('data.id');

        $foreignReward = LoyaltyReward::query()->create([
            'building_id' => $other['building']->id,
            'title' => 'Foreign reward',
            'required_points' => 1,
            'is_active' => true,
        ]);

        $invoice = $this->issuedInvoice(
            $graph['building'],
            $graph['unit'],
            500
        );

        Sanctum::actingAs($resident);
        $paymentId = $this->postJson(
            "/api/v1/invoices/{$invoice->id}/payments",
            [
                'amount' => 500,
                'method' => PaymentMethod::Manual->value,
            ]
        )
            ->assertCreated()
            ->json('data.id');

        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/payments/{$paymentId}/verify")
            ->assertOk();
        $this->postJson("/api/v1/payments/{$paymentId}/verify")
            ->assertOk();

        $account = LoyaltyAccount::query()->firstOrFail();
        $this->assertSame(10, (int) $account->balance);
        $this->assertDatabaseCount('loyalty_transactions', 1);

        Sanctum::actingAs($resident);
        $this->getJson('/api/v1/loyalty/me')
            ->assertOk()
            ->assertJsonPath('data.balance', 10);

        $this->getJson('/api/v1/loyalty/rewards')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $rewardId);

        $this->postJson(
            "/api/v1/loyalty/rewards/{$foreignReward->id}/claims",
            ['idempotency_key' => 'foreign-claim']
        )->assertUnprocessable();

        $claimId = $this->postJson(
            "/api/v1/loyalty/rewards/{$rewardId}/claims",
            ['idempotency_key' => 'reward-claim-1']
        )
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->json('data.id');

        $this->postJson(
            "/api/v1/loyalty/rewards/{$rewardId}/claims",
            ['idempotency_key' => 'reward-claim-1']
        )->assertCreated();

        $this->assertSame(6, (int) $account->refresh()->balance);
        $this->assertDatabaseCount('loyalty_reward_claims', 1);

        Sanctum::actingAs($manager);
        $this->getJson(
            "/api/v1/buildings/{$graph['building']->id}/loyalty-claims?status=pending"
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $claimId)
            ->assertJsonPath('data.0.status', 'pending');

        $this->postJson(
            "/api/v1/loyalty-claims/{$claimId}/reject",
            ['reason' => 'Unavailable']
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
        $this->postJson(
            "/api/v1/loyalty-claims/{$claimId}/reject",
            ['reason' => 'Unavailable']
        )->assertOk();

        $this->assertSame(10, (int) $account->refresh()->balance);
        $this->assertDatabaseCount('loyalty_transactions', 3);
    }

    public function test_penalties_are_audited_installment_safe_and_receipts_are_idempotent(): void
    {
        $graph = $this->createBuildingGraph();
        $manager = $this->createUser();
        $resident = $this->createUser();
        $this->grant(
            $manager,
            $graph['building'],
            [
                'invoices.update',
                'invoices.adjust',
                'payments.verify',
                'payments.view',
            ]
        );
        $this->occupy($resident, $graph['unit']);
        $invoice = $this->issuedInvoice(
            $graph['building'],
            $graph['unit'],
            1000
        );

        Sanctum::actingAs($manager);
        $this->putJson(
            "/api/v1/invoices/{$invoice->id}/installments",
            [
                'installments' => [
                    [
                        'due_date' => today()->addDays(10)->toDateString(),
                        'amount' => 400,
                    ],
                    [
                        'due_date' => today()->addDays(20)->toDateString(),
                        'amount' => 600,
                    ],
                ],
            ]
        )->assertOk();

        $this->postJson(
            "/api/v1/invoices/{$invoice->id}/penalty-adjustments",
            [
                'action' => 'add',
                'amount' => 100,
                'reason' => 'Late payment',
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.penalty_amount', 100)
            ->assertJsonPath('data.total_amount', 1100)
            ->assertJsonPath('data.installments.1.penalty_amount', 100);

        $this->postJson(
            "/api/v1/invoices/{$invoice->id}/penalty-adjustments",
            [
                'action' => 'waive',
                'amount' => 40,
                'reason' => 'Management approval',
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.penalty_amount', 60)
            ->assertJsonPath('data.waived_penalty_amount', 40)
            ->assertJsonPath('data.total_amount', 1060)
            ->assertJsonPath('data.installments.1.waived_amount', 40);

        $this->assertDatabaseHas('financial_audit_logs', [
            'action' => 'invoice.penalty.add',
            'entity_id' => $invoice->id,
            'user_id' => $manager->id,
        ]);
        $this->assertDatabaseHas('financial_audit_logs', [
            'action' => 'invoice.penalty.waive',
            'entity_id' => $invoice->id,
            'user_id' => $manager->id,
        ]);

        Sanctum::actingAs($resident);
        $paymentId = $this->postJson(
            "/api/v1/invoices/{$invoice->id}/payments",
            [
                'amount' => 1060,
                'method' => PaymentMethod::Manual->value,
            ]
        )
            ->assertCreated()
            ->json('data.id');

        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/payments/{$paymentId}/verify")
            ->assertOk();

        Sanctum::actingAs($resident);
        $receipt = $this->get("/api/v1/payments/{$paymentId}/receipt");
        $receipt
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $receipt->getContent());

        $this->get("/api/v1/payments/{$paymentId}/receipt")
            ->assertOk();
        $this->assertDatabaseCount('payment_receipts', 1);
    }

    public function test_expired_loyalty_points_are_removed_once(): void
    {
        $user = $this->createUser();
        $transaction = app(LoyaltyLedgerService::class)->earn(
            $user,
            8,
            'expiring-points',
            expiresAt: now()->subMinute()
        );

        $this->artisan('loyalty:expire-points')->assertSuccessful();
        $this->artisan('loyalty:expire-points')->assertSuccessful();

        $this->assertSame(
            0,
            (int) LoyaltyAccount::query()->firstOrFail()->balance
        );
        $this->assertSame(0, (int) $transaction->refresh()->remaining_points);
        $this->assertDatabaseCount('loyalty_transactions', 2);
    }

    public function test_legacy_loyalty_rows_are_backfilled_without_losing_balance(): void
    {
        $user = $this->createUser();
        $accountId = DB::table('loyalty_accounts')->insertGetId([
            'owner_type' => $user->getMorphClass(),
            'owner_id' => $user->id,
            'balance' => 6,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $earnId = DB::table('loyalty_transactions')->insertGetId([
            'loyalty_account_id' => $accountId,
            'type' => 'earn',
            'points' => 10,
            'description' => 'Legacy earn',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        $spendId = DB::table('loyalty_transactions')->insertGetId([
            'loyalty_account_id' => $accountId,
            'type' => 'spend',
            'points' => 4,
            'description' => 'Legacy positive spend',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path(
            'migrations/2026_08_20_240000_backfill_legacy_loyalty_ledger.php'
        );
        $migration->up();

        $this->assertDatabaseHas('loyalty_transactions', [
            'id' => $earnId,
            'points' => 10,
            'remaining_points' => 6,
            'balance_after' => 10,
        ]);
        $this->assertDatabaseHas('loyalty_transactions', [
            'id' => $spendId,
            'points' => -4,
            'remaining_points' => null,
            'balance_after' => 6,
        ]);
        $this->assertDatabaseHas('loyalty_transaction_allocations', [
            'spend_transaction_id' => $spendId,
            'earn_transaction_id' => $earnId,
            'points' => 4,
        ]);
    }

    private function issuedInvoice(
        Building $building,
        Unit $unit,
        int $amount
    ): UnitInvoice {
        return UnitInvoice::query()->create([
            'building_id' => $building->id,
            'unit_id' => $unit->id,
            'invoice_number' => 'TEST-'.str()->uuid(),
            'issue_date' => today(),
            'due_date' => today()->addMonth(),
            'subtotal' => $amount,
            'total_amount' => $amount,
            'outstanding_amount' => $amount,
            'status' => InvoiceStatus::Issued,
        ]);
    }

    private function occupy(User $user, Unit $unit): void
    {
        UnitOccupancy::query()->create([
            'unit_id' => $unit->id,
            'user_id' => $user->id,
            'occupancy_type' => OccupancyType::Resident,
            'starts_at' => today(),
            'is_primary' => true,
            'is_active' => true,
        ]);
    }

    /** @param array<int, string> $permissions */
    private function grant(
        User $user,
        Building $building,
        array $permissions
    ): void {
        $role = Role::query()->create([
            'name' => 'completion-'.str()->uuid(),
            'display_name' => 'Completion role',
            'is_system' => false,
        ]);

        foreach ($permissions as $name) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $name],
                [
                    'display_name' => $name,
                    'module' => explode('.', $name)[0],
                ]
            );
            $role->permissions()->syncWithoutDetaching($permission);
        }

        UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => $building->getMorphClass(),
            'scope_id' => $building->id,
            'starts_at' => now()->subMinute(),
            'is_active' => true,
        ]);
    }
}
