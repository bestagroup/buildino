<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\WalletTransferType;
use App\Models\InvoiceWalletSettlement;
use App\Models\PaymentAllocation;
use App\Models\Unit;
use App\Models\UnitInvoice;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** A presentation group over immutable invoice records; never duplicates debt. */
final class MonthlyUnitStatementService
{
    public function query(array $unitIds, ?User $resident = null): Builder
    {
        return UnitInvoice::query()->whereIn('unit_id', $unitIds)
            ->whereNull('start_charge_period_id')
            ->whereIn('status', ['issued', 'partial', 'paid', 'overdue'])
            ->when($resident, fn ($query) => $query->where(fn ($payer) => $payer
                ->whereNull('payer_user_id')->orWhere('payer_user_id', $resident->id)));
    }

    public function statements(array $unitIds, ?User $resident = null): Collection
    {
        return $this->query($unitIds, $resident)
            ->with(['unit', 'building', 'chargePeriod', 'invoiceItems'])->orderByDesc('period_start')->orderByDesc('id')
            ->get()->groupBy(fn (UnitInvoice $invoice) => $invoice->unit_id.':'.($invoice->charge_period_id ? 'period:'.$invoice->charge_period_id : 'legacy:'.$invoice->id))
            ->map(function (Collection $invoices): array {
                $first = $invoices->first();

                return [
                    'id' => $first->id, 'unit' => $first->unit, 'building' => $first->building,
                    'title' => $first->chargePeriod?->title ?: $first->invoice_number,
                    'invoices' => $invoices, 'items' => $invoices->flatMap->invoiceItems,
                    'total' => (int) $invoices->sum(fn ($invoice) => $invoice->total_amount < 0 ? -$invoice->credit_remaining_amount : $invoice->total_amount),
                    'consumed_credit' => (int) $invoices->sum(fn ($invoice) => $invoice->total_amount < 0 ? -$invoice->total_amount - $invoice->credit_remaining_amount : 0),
                    'paid' => (int) $invoices->sum('paid_amount'),
                    'outstanding' => (int) $invoices->sum('outstanding_amount'),
                    'credit' => (int) $invoices->sum('credit_applied_amount'),
                    'discount' => (int) $invoices->sum('discount_amount'),
                    'penalty' => (int) $invoices->sum('penalty_amount'),
                    'adjustment' => (int) $invoices->sum('adjustment_amount'),
                ];
            })->values();
    }

    /** Locks the entire unit before invoices and wallets; retries cannot debit twice. */
    public function settle(UnitInvoice $anchor, User $actor, ?User $resident = null): int
    {
        return DB::transaction(function () use ($anchor, $actor, $resident): int {
            $unit = Unit::query()->lockForUpdate()->findOrFail($anchor->unit_id);
            $invoices = $this->query([$unit->id], $resident)
                ->where('building_id', $anchor->building_id)
                ->when($anchor->charge_period_id,
                    fn ($query) => $query->where('charge_period_id', $anchor->charge_period_id),
                    fn ($query) => $query->whereKey($anchor->id))
                ->orderBy('id')->lockForUpdate()->get();
            abort_if($invoices->isEmpty(), 404);
            $payable = $invoices->filter(fn ($invoice) => $invoice->outstanding_amount > 0);
            $amount = (int) $payable->sum('outstanding_amount');
            if ($amount === 0) {
                return 0;
            }
            foreach ($payable as $invoice) {
                $pending = PaymentAllocation::query()->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
                    ->whereIn('payment_allocations.payable_type', array_unique([$invoice->getMorphClass(), UnitInvoice::class]))
                    ->where('payment_allocations.payable_id', $invoice->id)
                    ->whereIn('payments.status', [PaymentStatus::Pending->value, PaymentStatus::Processing->value])->exists();
                if ($pending) {
                    throw ValidationException::withMessages(['settlement' => 'این صورتحساب پرداخت در انتظار تأیید دارد؛ ابتدا وضعیت آن مشخص شود.']);
                }
            }
            $wallets = app(WalletService::class);
            $building = $unit->floor->block->building;
            abort_unless((int) $building->id === (int) $anchor->building_id, 404);
            $source = $wallets->walletFor($unit, $building->currency ?: 'IRR');
            $destination = $wallets->walletFor($building, $building->currency ?: 'IRR');
            $locked = Wallet::whereIn('id', [$source->id, $destination->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($locked[$source->id]->availableBalance() < $amount) {
                throw ValidationException::withMessages(['settlement' => 'موجودی کیف پول واحد کافی نیست؛ ابتدا کیف پول را شارژ کنید.']);
            }
            foreach ($payable as $invoice) {
                $due = (int) $invoice->outstanding_amount;
                $transfer = $wallets->transfer($source, $destination, $due, WalletTransferType::ChargeCollection,
                    'statement:invoice:'.$invoice->id.':paid:'.$invoice->paid_amount.':due:'.$due, $invoice, $actor, 'تسویه صورتحساب از کیف پول واحد');
                $settlement = InvoiceWalletSettlement::firstOrCreate(['wallet_transfer_id' => $transfer->id], [
                    'unit_invoice_id' => $invoice->id, 'source_wallet_id' => $source->id,
                    'destination_wallet_id' => $destination->id, 'amount' => $due,
                ]);
                if ($settlement->wasRecentlyCreated) {
                    app(InvoiceInstallmentService::class)->applyPayment($invoice, $due);
                    $invoice->update(['paid_amount' => $invoice->paid_amount + $due, 'outstanding_amount' => 0, 'status' => InvoiceStatus::Paid]);
                }
            }

            return $amount;
        }, 3);
    }
}
