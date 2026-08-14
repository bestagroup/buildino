<?php

namespace App\Services\Charge;

use App\Enums\ChargePolicyMode;
use App\Enums\InvoiceStatus;
use App\Enums\UnitChargePayerSource;
use App\Enums\WalletTransferType;
use App\Models\BuildingChargePolicy;
use App\Models\BuildingExpense;
use App\Models\BuildingExpenseAllocationRule;
use App\Models\ChargeExpenseAllocation;
use App\Models\ChargePeriod;
use App\Models\InvoiceWalletSettlement;
use App\Models\Unit;
use App\Models\UnitChargeSetting;
use App\Models\UnitInvoice;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WalletChargePeriodService
{
    public function __construct(
        private readonly ExpenseAllocationService $allocator,
        private readonly UnitChargePayerService $payerResolver,
        private readonly WalletService $wallets
    ) {
    }

    public function calculate(
        ChargePeriod $period,
        User $actor
    ): ChargePeriod {
        $policy = BuildingChargePolicy::query()
            ->where('building_id', $period->building_id)
            ->where('is_active', true)
            ->first();

        if (! $policy) {
            throw ValidationException::withMessages([
                'policy' => 'Building charge policy is not configured.',
            ]);
        }

        $units = $this->buildingUnits(
            $period->building_id
        );

        if ($units->isEmpty()) {
            throw ValidationException::withMessages([
                'units' => 'No active units exist in this building.',
            ]);
        }

        DB::transaction(function () use (
            $period,
            $policy,
            $units,
            $actor
        ): void {
            ChargeExpenseAllocation::query()
                ->where('charge_period_id', $period->getKey())
                ->delete();

            $unitLines = $units->mapWithKeys(
                fn (Unit $unit): array => [
                    $unit->getKey() => [],
                ]
            )->all();

            if (
                in_array(
                    $policy->mode,
                    [
                        ChargePolicyMode::Fixed,
                        ChargePolicyMode::Mixed,
                    ],
                    true
                )
                && $policy->fixed_monthly_amount > 0
            ) {
                foreach ($units as $unit) {
                    $unitLines[$unit->getKey()][] = [
                        'title' => 'Fixed monthly building charge',
                        'amount' => (int) $policy->fixed_monthly_amount,
                        'metadata' => [
                            'source' => 'fixed_monthly_charge',
                            'policy_id' => $policy->getKey(),
                        ],
                    ];
                }
            }

            if (
                in_array(
                    $policy->mode,
                    [
                        ChargePolicyMode::SharedExpenses,
                        ChargePolicyMode::Mixed,
                    ],
                    true
                )
            ) {
                $expenses = BuildingExpense::query()
                    ->where('building_id', $period->building_id)
                    ->where('status', 'posted')
                    ->whereDate(
                        'expense_date',
                        '>=',
                        $period->period_start->toDateString()
                    )
                    ->whereDate(
                        'expense_date',
                        '<=',
                        $period->period_end->toDateString()
                    )
                    ->orderBy('id')
                    ->get();

                foreach ($expenses as $expense) {
                    if (! $expense->financial_category_id) {
                        throw ValidationException::withMessages([
                            'financial_category_id' => sprintf(
                                'Posted expense %d has no financial category.',
                                $expense->getKey()
                            ),
                        ]);
                    }

                    $rule = BuildingExpenseAllocationRule::query()
                        ->where(
                            'building_id',
                            $period->building_id
                        )
                        ->where(
                            'financial_category_id',
                            $expense->financial_category_id
                        )
                        ->where('is_active', true)
                        ->first();

                    if (! $rule) {
                        throw ValidationException::withMessages([
                            'allocation_rule' => sprintf(
                                'No active allocation rule is configured for financial category %d.',
                                $expense->financial_category_id
                            ),
                        ]);
                    }

                    $rows = $this->allocator->allocate(
                        $expense,
                        $rule,
                        $period,
                        $units
                    );

                    foreach ($rows as $row) {
                        ChargeExpenseAllocation::query()->create([
                            'charge_period_id' => $period->getKey(),
                            'building_expense_id' => $expense->getKey(),
                            'unit_id' => $row['unit']->getKey(),
                            'building_expense_allocation_rule_id' =>
                                $rule->getKey(),
                            'base_value' => $row['base_value'],
                            'allocated_amount' => $row['amount'],
                            'calculation_snapshot' => [
                                'method' => $rule->allocation_method->value,
                                'category_id' => $expense->financial_category_id,
                                'expense_amount' => (int) $expense->amount,
                                'base_value' => $row['base_value'],
                            ],
                        ]);

                        if ($row['amount'] > 0) {
                            $unitLines[$row['unit']->getKey()][] = [
                                'title' => $expense->title,
                                'amount' => $row['amount'],
                                'metadata' => [
                                    'source' => 'building_expense',
                                    'building_expense_id' => $expense->getKey(),
                                    'financial_category_id' =>
                                        $expense->financial_category_id,
                                    'allocation_rule_id' => $rule->getKey(),
                                    'allocation_method' =>
                                        $rule->allocation_method->value,
                                    'base_value' => $row['base_value'],
                                ],
                            ];
                        }
                    }
                }
            }

            foreach ($units as $unit) {
                $this->upsertDraftInvoice(
                    $period,
                    $unit,
                    $unitLines[$unit->getKey()],
                    $actor
                );
            }

            $period->update([
                'status' => 'calculated',
            ]);
        });

        return $period->refresh();
    }

    public function issueAndCollect(
        ChargePeriod $period,
        User $actor
    ): ChargePeriod {
        $periodStatus = is_object($period->status)
            ? $period->status->value
            : (string) $period->status;

        if ($periodStatus !== 'calculated') {
            throw ValidationException::withMessages([
                'status' => 'Charge period must be calculated before issue.',
            ]);
        }

        $policy = BuildingChargePolicy::query()
            ->where('building_id', $period->building_id)
            ->where('is_active', true)
            ->firstOrFail();

        DB::transaction(function () use (
            $period,
            $policy,
            $actor
        ): void {
            $invoices = UnitInvoice::query()
                ->where('charge_period_id', $period->getKey())
                ->with('unit')
                ->lockForUpdate()
                ->get();

            foreach ($invoices as $invoice) {
                if ($invoice->status === InvoiceStatus::Draft) {
                    $invoice->update([
                        'status' => InvoiceStatus::Issued,
                        'outstanding_amount' => $invoice->total_amount,
                    ]);
                }
            }

            $period->update([
                'status' => 'issued',
            ]);
        });

        if ($policy->auto_collect) {
            $invoices = UnitInvoice::query()
                ->where('charge_period_id', $period->getKey())
                ->with('unit')
                ->get();

            foreach ($invoices as $invoice) {
                $this->collectInvoice(
                    $invoice,
                    $actor,
                    $policy->allow_partial
                );
            }
        }

        return $period->refresh();
    }

    public function collectInvoice(
        UnitInvoice $invoice,
        User $actor,
        ?bool $allowPartialOverride = null
    ): UnitInvoice {
        $invoice->refresh();
        $invoice->loadMissing([
            'unit.floor.block.building',
        ]);

        if (
            ! in_array(
                $invoice->status,
                [
                    InvoiceStatus::Issued,
                    InvoiceStatus::Partial,
                    InvoiceStatus::Overdue,
                ],
                true
            )
        ) {
            return $invoice;
        }

        if ($invoice->outstanding_amount <= 0) {
            return $invoice;
        }

        $setting = UnitChargeSetting::query()
            ->where('unit_id', $invoice->unit_id)
            ->first();

        $source = $this->payerResolver->resolveWallet(
            $invoice->unit
        );

        $building = $invoice->unit
            ->floor
            ?->block
            ?->building;

        if (! $building) {
            throw ValidationException::withMessages([
                'building' => 'Invoice unit is not connected to a building.',
            ]);
        }

        $destination = $this->wallets->walletFor(
            $building
        );

        $allowPartial = $allowPartialOverride
            ?? $setting?->allow_partial
            ?? true;

        $available = $source->fresh()->availableBalance();
        $outstanding = (int) $invoice->outstanding_amount;

        if ($available <= 0) {
            return $invoice;
        }

        if (! $allowPartial && $available < $outstanding) {
            return $invoice;
        }

        $amount = $allowPartial
            ? min($available, $outstanding)
            : $outstanding;

        $transfer = $this->wallets->transfer(
            $source,
            $destination,
            $amount,
            WalletTransferType::ChargeCollection,
            sprintf(
                'invoice:%d:wallet-collect:paid:%d:amount:%d',
                $invoice->getKey(),
                (int) $invoice->paid_amount,
                $amount
            ),
            $invoice,
            $actor,
            'Automatic building charge collection'
        );

        /*
         * If the idempotency key already existed, do not settle
         * the same transfer twice.
         */
        $settlement = InvoiceWalletSettlement::query()
            ->firstOrCreate(
                [
                    'wallet_transfer_id' => $transfer->getKey(),
                ],
                [
                    'unit_invoice_id' => $invoice->getKey(),
                    'source_wallet_id' => $source->getKey(),
                    'destination_wallet_id' => $destination->getKey(),
                    'amount' => $amount,
                ]
            );

        if (! $settlement->wasRecentlyCreated) {
            return $invoice->refresh();
        }

        $newPaid = min(
            (int) $invoice->total_amount,
            (int) $invoice->paid_amount + $amount
        );

        $newOutstanding = max(
            0,
            (int) $invoice->total_amount - $newPaid
        );

        $invoice->update([
            'paid_amount' => $newPaid,
            'outstanding_amount' => $newOutstanding,
            'status' => $newOutstanding === 0
                ? InvoiceStatus::Paid
                : InvoiceStatus::Partial,
        ]);

        return $invoice->refresh();
    }

    private function buildingUnits(int $buildingId): Collection
    {
        return Unit::query()
            ->where('is_active', true)
            ->whereHas(
                'floor.block',
                fn (Builder $query) =>
                    $query->where(
                        'building_id',
                        $buildingId
                    )
            )
            ->orderBy('id')
            ->get();
    }

    private function upsertDraftInvoice(
        ChargePeriod $period,
        Unit $unit,
        array $lines,
        User $actor
    ): UnitInvoice {
        $invoice = UnitInvoice::query()->firstOrNew([
            'building_id' => $period->building_id,
            'unit_id' => $unit->getKey(),
            'charge_period_id' => $period->getKey(),
        ]);

        if (
            $invoice->exists
            && $invoice->status !== InvoiceStatus::Draft
        ) {
            throw ValidationException::withMessages([
                'invoice' => sprintf(
                    'Invoice %d is already issued and cannot be recalculated.',
                    $invoice->getKey()
                ),
            ]);
        }

        $subtotal = array_sum(
            array_column($lines, 'amount')
        );

        $invoice->fill([
            'invoice_number' =>
                $invoice->invoice_number
                ?: sprintf(
                    'WCH-%d-%d-%d',
                    $period->building_id,
                    $period->getKey(),
                    $unit->getKey()
                ),
            'issue_date' => now()->toDateString(),
            'due_date' => $period->due_date,
            'period_start' => $period->period_start,
            'period_end' => $period->period_end,
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'penalty_amount' => 0,
            'total_amount' => $subtotal,
            'paid_amount' => 0,
            'outstanding_amount' => $subtotal,
            'status' => InvoiceStatus::Draft,
            'description' => $period->title,
            'created_by' => $actor->getKey(),
        ]);

        $invoice->save();

        $invoice->invoiceItems()->delete();

        foreach ($lines as $line) {
            $invoice->invoiceItems()->create([
                'charge_item_id' => null,
                'title' => $line['title'],
                'description' => null,
                'quantity' => 1,
                'unit_amount' => $line['amount'],
                'total_amount' => $line['amount'],
                'metadata' => $line['metadata'],
            ]);
        }

        return $invoice->refresh();
    }
}
