<?php

namespace Tests\Feature\Financial;

use App\Enums\WalletTransferType;
use App\Models\BuildingChargePolicy;
use App\Models\ChargePeriod;
use App\Models\FileRelation;
use App\Models\InvoiceWalletSettlement;
use App\Models\Payment;
use App\Models\UnitInvoice;
use App\Models\UnitOwnership;
use App\Models\User;
use App\Models\WalletTopUp;
use App\Services\InvoiceCreditService;
use App\Services\MonthlyUnitStatementService;
use App\Services\Wallet\WalletService;
use Database\Seeders\AccessScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MonthlyUnitStatementTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $this->seed(AccessScenarioSeeder::class);
        $owner = User::where('email', 'role.owner@buildino.local')->firstOrFail();
        $manager = User::where('email', 'role.building@buildino.local')->firstOrFail();
        $unit = UnitOwnership::where('user_id', $owner->id)->firstOrFail()->unit;
        $building = $unit->floor->block->building;
        $period = ChargePeriod::create(['building_id' => $building->id, 'title' => 'مهر ۱۴۰۵', 'period_start' => '2026-09-23', 'period_end' => '2026-10-22', 'due_date' => '2026-10-25', 'status' => 'issued', 'created_by' => $manager->id]);

        return [$owner, $manager, $unit, $building, $period];
    }

    private function invoice($unit, $building, $period, int $amount, ?int $payer = null): UnitInvoice
    {
        $invoice = UnitInvoice::create(['building_id' => $building->id, 'unit_id' => $unit->id, 'charge_period_id' => $period->id,
            'invoice_number' => 'TEST-'.Str::uuid(), 'payer_responsibility' => $payer ? 'owner' : 'unit', 'payer_user_id' => $payer,
            'issue_date' => today(), 'due_date' => today()->addDays(5), 'period_start' => $period->period_start, 'period_end' => $period->period_end,
            'subtotal' => $amount, 'total_amount' => $amount, 'outstanding_amount' => $amount, 'paid_amount' => 0, 'status' => 'issued']);
        $invoice->invoiceItems()->create(['title' => 'هزینه آزمایشی', 'quantity' => 2, 'unit_amount' => $amount / 2, 'total_amount' => $amount]);

        return $invoice;
    }

    public function test_monthly_group_keeps_line_items_and_excludes_drafts_and_other_person_debt(): void
    {
        [$owner, $manager, $unit, $building, $period] = $this->scenario();
        $this->invoice($unit, $building, $period, 1000);
        $this->invoice($unit, $building, $period, 2000, $owner->id);
        $this->invoice($unit, $building, $period, 4000, $manager->id);
        $this->invoice($unit, $building, $period, 8000)->update(['status' => 'draft']);
        $service = app(MonthlyUnitStatementService::class);
        $resident = $service->statements([$unit->id], $owner)->where('title', 'مهر ۱۴۰۵')->values();
        $this->assertCount(1, $resident);
        $this->assertSame(3000, $resident[0]['total']);
        $this->assertCount(2, $resident[0]['items']);
        $this->assertSame(7000, $service->statements([$unit->id])->firstWhere('title', 'مهر ۱۴۰۵')['total']);
        $this->actingAs($owner)->get('/portal/resident/operations/invoices')->assertOk()->assertSee('مهر ۱۴۰۵')->assertSee('تسویه از کیف پول واحد');
        $this->actingAs($manager)->get(route('management.unit-statements', $unit))->assertOk()->assertSee('مبلغ واحد');
    }

    public function test_settlement_transfers_exact_monthly_balance_once_and_keeps_other_months(): void
    {
        [$owner, $manager, $unit, $building, $period] = $this->scenario();
        $a = $this->invoice($unit, $building, $period, 1000);
        $b = $this->invoice($unit, $building, $period, 2000, $owner->id);
        $wallets = app(WalletService::class);
        $source = $wallets->walletFor($unit);
        $destination = $wallets->walletFor($building);
        $wallets->credit($source, 5000, WalletTransferType::TopUp, 'test-funds');
        $before = $destination->fresh()->balance;
        $service = app(MonthlyUnitStatementService::class);
        $this->assertSame(3000, $service->settle($a, $owner, $owner));
        $this->assertSame(0, $service->settle($b, $owner, $owner));
        $this->assertSame(2000, $source->fresh()->balance);
        $this->assertSame($before + 3000, $destination->fresh()->balance);
        $this->assertSame(0, $a->fresh()->outstanding_amount);
        $this->assertSame(0, $b->fresh()->outstanding_amount);
        $this->assertSame(2, InvoiceWalletSettlement::count());
    }

    public function test_insufficient_funds_roll_back_all_invoices(): void
    {
        [$owner, $manager, $unit, $building, $period] = $this->scenario();
        $a = $this->invoice($unit, $building, $period, 1000);
        $this->invoice($unit, $building, $period, 2000);
        $wallet = app(WalletService::class)->walletFor($unit);
        app(WalletService::class)->credit($wallet, 1500, WalletTransferType::TopUp, 'insufficient');
        try {
            app(MonthlyUnitStatementService::class)->settle($a, $owner, $owner);
            $this->fail('Accepted insufficient balance');
        } catch (ValidationException) {
            $this->assertSame(1500, $wallet->fresh()->balance);
            $this->assertSame(1000, $a->fresh()->outstanding_amount);
        }
        $this->assertSame(0, InvoiceWalletSettlement::count());
    }

    public function test_unrelated_user_cannot_view_or_settle_unit_statements(): void
    {
        [$owner, $manager, $unit, $building, $period] = $this->scenario();
        $invoice = $this->invoice($unit, $building, $period, 1000);
        $other = User::factory()->create();
        $this->actingAs($other)->post(route('portal.resident.statements.settle', $invoice))->assertForbidden();
        $this->actingAs($owner)->get(route('management.unit-statements', $unit))->assertRedirect();
    }

    public function test_consumed_credit_is_not_deducted_twice_in_monthly_total(): void
    {
        [$owner, $manager, $unit, $building, $period] = $this->scenario();
        // Remove unrelated fixture debt so the credit is applied to this period.
        UnitInvoice::where('unit_id', $unit->id)->delete();
        $debit = $this->invoice($unit, $building, $period, 1000);
        $credit = $this->invoice($unit, $building, $period, -200);
        $credit->forceFill(['outstanding_amount' => 0, 'credit_remaining_amount' => 200])->save();
        app(InvoiceCreditService::class)->apply($unit->id, $building->id);
        $statement = app(MonthlyUnitStatementService::class)->statements([$unit->id], $owner)->first();
        $this->assertSame(800, $statement['total']);
        $this->assertSame(800, $statement['outstanding']);
        $this->assertSame(200, $statement['consumed_credit']);
        $this->assertSame(200, $statement['credit']);
    }

    public function test_pending_payment_prevents_wallet_double_collection(): void
    {
        [$owner, $manager, $unit, $building, $period] = $this->scenario();
        $invoice = $this->invoice($unit, $building, $period, 1000);
        $payment = Payment::create(['uuid' => (string) Str::uuid(), 'building_id' => $building->id, 'payer_user_id' => $owner->id,
            'payment_number' => 'PENDING-TEST', 'amount' => 1000, 'currency' => 'IRR', 'method' => 'online', 'status' => 'pending']);
        $invoice->paymentAllocations()->create(['payment_id' => $payment->id, 'amount' => 1000]);
        $wallet = app(WalletService::class)->walletFor($unit);
        app(WalletService::class)->credit($wallet, 2000, WalletTransferType::TopUp, 'pending-funds');
        $this->actingAs($owner)->postJson(route('portal.resident.statements.settle', $invoice))->assertUnprocessable();
        $this->assertSame(2000, $wallet->fresh()->balance);
        $this->assertSame(1000, $invoice->fresh()->outstanding_amount);
    }

    public function test_rejected_receipt_rolls_back_wallet_credit_and_payment(): void
    {
        [$owner, $manager, $unit, $building] = $this->scenario();
        Storage::fake('private');
        $this->mock(\App\Contracts\FileScanner::class, function ($mock) {
            $mock->shouldReceive('scan')->once()->andReturn(\App\Enums\FileScanStatus::Infected);
        });
        $this->actingAs($manager)->post(route('management.unit-deposits', $unit), [
            'amount' => 1000, 'reference' => 'REJECTED', 'idempotency_key' => (string) Str::uuid(),
            'receipt' => UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n%%EOF"),
        ])->assertSessionHasErrors('file');
        $this->assertSame(0, \App\Models\WalletTopUp::count());
        $this->assertSame(0, app(WalletService::class)->walletFor($unit)->balance);
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_manager_deposit_requires_receipt_and_is_idempotent_by_bank_reference(): void
    {
        [$owner, $manager, $unit, $building, $period] = $this->scenario();
        Storage::fake('private');
        $invoice = $this->invoice($unit, $building, $period, 1000);
        BuildingChargePolicy::updateOrCreate(['building_id' => $building->id], ['mode' => 'fixed', 'is_active' => true, 'auto_collect' => true, 'allow_partial' => true, 'fixed_monthly_amount' => 1000]);
        $destination = app(WalletService::class)->walletFor($building);
        $buildingBalance = $destination->balance;
        $data = ['amount' => 4500, 'reference' => 'BANK-TEST-123', 'idempotency_key' => (string) Str::uuid(), 'receipt' => UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF")];
        $url = route('management.unit-deposits', $unit);
        $this->actingAs($owner)->post($url, $data)->assertRedirect();
        $this->actingAs($manager)->post($url, array_diff_key($data, ['receipt' => 1]))->assertStatus(302)->assertSessionHasErrors('receipt');
        $this->actingAs($manager)->post($url, $data)->assertSessionHasNoErrors()->assertRedirect();
        $data['idempotency_key'] = (string) Str::uuid();
        $this->post($url, $data)->assertSessionHasNoErrors()->assertRedirect();
        $data['amount'] = 9000;
        $this->post($url, $data)->assertSessionHasErrors('idempotency_key');
        $this->assertSame(4500, app(WalletService::class)->walletFor($unit)->balance);
        $this->assertSame(1000, $invoice->fresh()->outstanding_amount);
        $this->assertSame($buildingBalance, $destination->fresh()->balance);
        $this->assertSame(1, WalletTopUp::count());
        $this->assertSame(1, FileRelation::where('purpose', 'unit_wallet_deposit')->count());
    }
}
