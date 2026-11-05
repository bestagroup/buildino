<?php

namespace App\Services;

use App\Enums\ChargeCalculationType;
use App\Enums\ChargePeriodStatus;
use App\Enums\ExpensePayerResponsibility;
use App\Enums\InvoiceStatus;
use App\Models\BuildingExpense;
use App\Models\ChargeExpenseAllocation;
use App\Models\ChargeCalculation;
use App\Models\ChargeFormula;
use App\Models\ChargePeriod;
use App\Models\Unit;
use App\Models\UnitInvoice;
use App\Models\User;
use App\Services\Charge\BuildingExpenseRuleService;
use App\Services\Charge\ExpenseAllocationService;
use App\Services\Charge\ExpensePayerResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChargePeriodService
{
    public function __construct(
        private readonly ChargeService $charges,
        private readonly InvoiceService $invoices,
        private readonly ExpenseAllocationService $expenseAllocator,
        private readonly ExpensePayerResolver $expensePayers,
        private readonly BuildingExpenseRuleService $expenseRules
    ) {}

    private function periodFormulas(ChargePeriod $period): Builder
    {
        return ChargeFormula::query()->where('building_id', $period->building_id)
            ->where('is_active', true)->with('chargeItems')->orderBy('id');
    }

    private function periodExpenses(ChargePeriod $period): Builder
    {
        return BuildingExpense::query()->where('building_id', $period->building_id)
            ->whereDate('expense_date', '>=', $period->period_start->toDateString())
            ->whereDate('expense_date', '<=', $period->period_end->toDateString())->orderBy('id');
    }

    private function periodUnits(ChargePeriod $period): Builder
    {
        return Unit::query()->with('floor:id,block_id,floor_number')->where('is_active', true)
            ->whereHas('floor.block', fn (Builder $query) => $query->where('building_id', $period->building_id))
            ->orderBy('id');
    }

    /** Read-only inventory of the exact sources used by calculate(). */
    public function preview(ChargePeriod $period): array
    {
        return DB::transaction(function () use ($period): array {
            $period = $this->lockPeriodAndUnits($period);

            return $this->buildPreview($period);
        }, 3);
    }

    private function lockPeriodAndUnits(ChargePeriod $period): ChargePeriod
    {
        $period = ChargePeriod::query()->lockForUpdate()->findOrFail($period->getKey());
        // Occupancy, ownership and resident-count writers already lock their unit.
        // Use the same mutex, in stable order, before any snapshot reads.
        // A WHERE EXISTS subquery can open an InnoDB repeatable-read snapshot
        // before waiting on the unit lock. Lock joined rows instead, so subsequent
        // reads see the resident writer's committed data after the wait.
        Unit::query()->select('units.*')
            ->join('floors', 'floors.id', '=', 'units.floor_id')
            ->join('blocks', 'blocks.id', '=', 'floors.block_id')
            ->where('blocks.building_id', $period->building_id)
            ->orderBy('units.id')->lockForUpdate()->get();

        return $period;
    }

    private function sourceFingerprint(ChargePeriod $period): string
    {
        $units = $this->periodUnits($period)->get();
        $unitIds = $units->modelKeys();
        $relationships = [];
        foreach (['unit_occupancies', 'unit_ownerships'] as $table) {
            $relationships[$table] = DB::table($table)->whereIn('unit_id', $unitIds)
                ->whereDate('starts_at', '<=', $period->period_end->toDateString())
                ->where(fn ($query) => $query->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', $period->period_start->toDateString()))
                ->orderBy('id')->get()->all();
        }
        $relationships['resident_counts'] = DB::table('unit_resident_counts')->whereIn('unit_id', $unitIds)
            ->whereDate('effective_from', '<=', $period->period_end->toDateString())
            ->orderBy('unit_id')->orderBy('effective_from')->get()->all();

        return hash('sha256', json_encode([
            $period->only(['id', 'building_id', 'title', 'period_start', 'period_end', 'due_date']),
            $period->building->currency,
            $this->periodFormulas($period)->get()->toArray(),
            $this->periodExpenses($period)->get()->toArray(),
            $units->toArray(), $relationships,
        ], JSON_THROW_ON_ERROR));
    }

    private function buildPreview(ChargePeriod $period): array
    {
        if (! in_array($period->status, [ChargePeriodStatus::Draft, ChargePeriodStatus::Calculated], true)) {
            throw ValidationException::withMessages(['status' => 'Only draft or calculated charge periods can be recalculated.']);
        }
        $formulas = $this->periodFormulas($period)->get();
        $expenses = $this->periodExpenses($period)->with('category')->get();
        $units = $this->periodUnits($period)->get();
        $errors = [];
        $byCategory = collect();
        foreach ($formulas as $formula) {
            $category = $formula->chargeItems->first()?->financial_category_id;
            if ($formula->chargeItems->count() !== 1 || ! $category || $byCategory->has($category)) {
                $errors[] = "اتصال دسته هزینه یا یکتایی فرمول فعال «{$formula->title}» را اصلاح کنید.";
            } else {
                $byCategory->put($category, $formula);
            }
        }
        $fixed = $formulas->filter(fn ($formula) => $formula->calculation_type === ChargeCalculationType::Fixed);
        $posted = $expenses->where('status', 'posted');
        foreach ($posted as $expense) {
            $formula = $byCategory->get($expense->financial_category_id);
            if (! $formula || $formula->calculation_type === ChargeCalculationType::Fixed) {
                $errors[] = "برای هزینه «{$expense->title}» فرمول فعال غیرثابت و معتبر لازم است.";
            }
        }
        if ($units->isEmpty()) $errors[] = 'هیچ واحد فعالی در ساختمان وجود ندارد.';
        if ($formulas->isEmpty()) $errors[] = 'هیچ فرمول فعالی برای ساختمان تعریف نشده است.';
        if ($posted->isEmpty() && $fixed->isEmpty()) $errors[] = 'هیچ هزینه قطعی یا فرمول ثابت برای این دوره وجود ندارد.';
        $expenseRow = fn ($expense) => [
            'id' => $expense->id, 'title' => $expense->title,
            'category' => $expense->category?->title,
            'invoice_number' => $expense->invoice_number,
            'date' => $expense->expense_date->toDateString(), 'amount' => (int) $expense->amount,
            'status' => $expense->status, 'payment_status' => $expense->payment_status,
            'formula' => $byCategory->get($expense->financial_category_id)?->title,
        ];
        $data = [
            'currency' => $period->building->currency,
            'unit_count' => $units->count(),
            'expenses' => $posted->map($expenseRow)->values()->all(),
            'excluded_expenses' => $expenses->where('status', '!=', 'posted')->map($expenseRow)->values()->all(),
            'fixed_formulas' => $fixed->map(fn ($formula) => [
                'id' => $formula->id, 'title' => $formula->title,
                'per_unit' => (int) $formula->chargeItems->sum('base_amount'),
                'total' => (int) $formula->chargeItems->sum('base_amount') * $units->count(),
            ])->values()->all(),
            'formulas_without_expenses' => $formulas->filter(fn ($formula) =>
                $formula->calculation_type !== ChargeCalculationType::Fixed
                && ! $posted->contains('financial_category_id', $formula->chargeItems->first()?->financial_category_id)
            )->pluck('title')->values()->all(),
            'expense_total' => (int) $posted->sum('amount'),
            'fixed_total' => (int) $fixed->sum(fn ($formula) => $formula->chargeItems->sum('base_amount')) * $units->count(),
            'existing_invoices' => $period->unitInvoices()->orderBy('id')->get()->map(fn ($invoice) => [
                'number' => $invoice->invoice_number, 'unit_id' => $invoice->unit_id,
                'status' => $invoice->status->value, 'total' => (int) $invoice->total_amount,
            ])->all(),
            'errors' => $errors,
        ];
        // The revision also invalidates two confirmations made within the same second.
        $data['review_token'] = hash('sha256', $this->sourceFingerprint($period).':'.$period->calculation_revision);

        return $data;
    }

    public function calculate(ChargePeriod $period, string $reviewToken): ChargePeriod
    {
        DB::transaction(function () use ($period, $reviewToken): void {
            $period = $this->lockPeriodAndUnits($period);
            $preview = $this->buildPreview($period);
            if (! hash_equals($preview['review_token'], $reviewToken)) {
                throw ValidationException::withMessages([
                    'review_token' => 'اطلاعات دوره یا ساکنان پس از بازبینی تغییر کرده است؛ دوباره فهرست اقلام را بررسی کنید.',
                ]);
            }
            $period->update(['status' => ChargePeriodStatus::Calculating]);
            $formulas = $this->periodFormulas($period)->get();

            if ($formulas->isEmpty()) {
                throw ValidationException::withMessages([
                    'formulas' =>
                        'برای این ساختمان هیچ فرمول شارژ فعالی تعریف نشده است.',
                ]);
            }

            $formulaByCategory = collect();
            $fixedFormulas = collect();

            foreach ($formulas as $formula) {
                if ($formula->chargeItems->count() !== 1) {
                    throw ValidationException::withMessages([
                        'formulas' => sprintf(
                            'فرمول %d باید دقیقاً به یک دسته هزینه متصل باشد.',
                            $formula->getKey()
                        ),
                    ]);
                }

                $item = $formula->chargeItems->first();

                if (! $item?->financial_category_id) {
                    throw ValidationException::withMessages([
                        'formulas' => sprintf(
                            'برای فرمول %d دسته هزینه مشخص نشده است.',
                            $formula->getKey()
                        ),
                    ]);
                }

                $categoryId =
                    (int) $item->financial_category_id;

                if ($formulaByCategory->has($categoryId)) {
                    throw ValidationException::withMessages([
                        'formulas' => sprintf(
                            'برای دسته هزینه %d بیش از یک فرمول فعال وجود دارد.',
                            $categoryId
                        ),
                    ]);
                }

                $formulaByCategory->put(
                    $categoryId,
                    $formula
                );

                $type = $formula->calculation_type
                    instanceof ChargeCalculationType
                        ? $formula->calculation_type
                        : ChargeCalculationType::from(
                            (string) $formula->calculation_type
                        );

                if ($type === ChargeCalculationType::Fixed) {
                    $fixedFormulas->push($formula);
                }
            }

            $expenses = $this->periodExpenses($period)->where('status', 'posted')->get();

            if (
                $fixedFormulas->isEmpty()
                && $expenses->isEmpty()
            ) {
                throw ValidationException::withMessages([
                    'expenses' =>
                        'برای این دوره هیچ هزینه ثبت‌شده یا فرمول مبلغ ثابت وجود ندارد.',
                ]);
            }

            $units = $this->periodUnits($period)->get();

            if ($units->isEmpty()) {
                throw ValidationException::withMessages([
                    'units' =>
                        'No active unit exists for this building.',
                ]);
            }

            ChargeCalculation::query()
                ->where(
                    'charge_period_id',
                    $period->getKey()
                )
                ->delete();

            ChargeExpenseAllocation::query()
                ->where(
                    'charge_period_id',
                    $period->getKey()
                )
                ->delete();

            /*
             * Fixed formulas are the only formulas that create an amount
             * without a period expense. Every active unit receives the
             * configured fixed debt.
             */
            foreach ($fixedFormulas as $formula) {
                foreach ($units as $unit) {
                    $result =
                        $this->charges
                            ->calculateBreakdown(
                                $formula,
                                $unit,
                                $period->period_start,
                                $period->period_end
                            );

                    ChargeCalculation::query()
                        ->create([
                            'charge_period_id' =>
                                $period->getKey(),
                            'unit_id' =>
                                $unit->getKey(),
                            'charge_formula_id' =>
                                $formula->getKey(),
                            'base_value' =>
                                $result['base_value'],
                            'calculated_amount' =>
                                $result['amount'],
                            'calculation_snapshot' =>
                                $result['snapshot'],
                        ]);
                }
            }

            /*
             * All other formulas are allocation rules. The amount comes
             * from the posted expense in this period and is distributed
             * according to the formula configured once for the category.
             */
            foreach ($expenses as $expense) {
                if (! $expense->financial_category_id) {
                    throw ValidationException::withMessages([
                        'financial_category_id' => sprintf(
                            'برای هزینه %d دسته هزینه مشخص نشده است.',
                            $expense->getKey()
                        ),
                    ]);
                }

                $formula = $formulaByCategory->get(
                    (int) $expense
                        ->financial_category_id
                );

                if (! $formula) {
                    throw ValidationException::withMessages([
                        'formulas' => sprintf(
                            'برای دسته هزینه مربوط به «%s» فرمول شارژ فعال تعریف نشده است.',
                            $expense->title
                        ),
                    ]);
                }

                $type = $formula->calculation_type
                    instanceof ChargeCalculationType
                        ? $formula->calculation_type
                        : ChargeCalculationType::from(
                            (string) $formula
                                ->calculation_type
                        );

                if ($type === ChargeCalculationType::Fixed) {
                    throw ValidationException::withMessages([
                        'formulas' => sprintf(
                            'فرمول ثابت «%s» نباید برای تقسیم مبلغ یک هزینه دوره استفاده شود.',
                            $formula->title
                        ),
                    ]);
                }

                $rule = $this->expenseRules
                    ->synchronizeFromFormula(
                        $expense,
                        $formula
                    );

                $expenseUnits = $expense->block_id
                    ? $units
                        ->filter(
                            fn (Unit $unit): bool =>
                                (int) $unit
                                    ->floor
                                    ?->block_id
                                === (int) $expense
                                    ->block_id
                        )
                        ->values()
                    : $units;

                if ($expenseUnits->isEmpty()) {
                    throw ValidationException::withMessages([
                        'block_id' => sprintf(
                            'در دامنه هزینه %d هیچ واحد فعالی وجود ندارد.',
                            $expense->getKey()
                        ),
                    ]);
                }

                $responsibility =
                    $expense->payer_responsibility
                        instanceof ExpensePayerResponsibility
                            ? $expense
                                ->payer_responsibility
                            : ExpensePayerResponsibility::from(
                                (string) $expense
                                    ->payer_responsibility
                            );

                $rows = $this->expenseAllocator
                    ->allocateByFormula(
                        $expense,
                        $formula,
                        $period,
                        $expenseUnits
                    );

                foreach ($rows as $row) {
                    $payer = $row['amount'] > 0
                        ? $this->expensePayers
                            ->resolve(
                                $row['unit'],
                                $responsibility,
                                $period
                            )
                        : null;

                    ChargeExpenseAllocation::query()
                        ->create([
                            'charge_period_id' =>
                                $period->getKey(),
                            'building_expense_id' =>
                                $expense->getKey(),
                            'unit_id' =>
                                $row['unit']->getKey(),
                            'building_expense_allocation_rule_id' =>
                                $rule->getKey(),
                            'payer_responsibility' =>
                                $responsibility,
                            'payer_user_id' =>
                                $payer?->getKey(),
                            'base_value' =>
                                $row['base_value'],
                            'allocated_amount' =>
                                $row['amount'],
                            'calculation_snapshot' => [
                                'source' =>
                                    'building_expense',
                                'expense_id' =>
                                    $expense->getKey(),
                                'expense_amount' =>
                                    (int) $expense->amount,
                                'financial_category_id' =>
                                    $expense
                                        ->financial_category_id,
                                'block_id' =>
                                    $expense->block_id,
                                'charge_formula_id' =>
                                    $formula->getKey(),
                                'calculation_type' =>
                                    $type->value,
                                'payer_responsibility' =>
                                    $responsibility->value,
                                'payer_user_id' =>
                                    $payer?->getKey(),
                                'base_value' =>
                                    $row['base_value'],
                            ],
                        ]);
                }
            }

            $period->forceFill([
                'status' => ChargePeriodStatus::Calculated,
                'calculation_source_hash' => $this->sourceFingerprint($period),
                'calculation_revision' => $period->calculation_revision + 1,
            ])->save();
        }, 3);

        return $period->refresh();
    }

    public function issue(
        ChargePeriod $period,
        User $actor
    ): ChargePeriod {
        DB::transaction(function () use ($period, $actor): void {
            $period = $this->lockPeriodAndUnits($period);
            if ($period->status !== ChargePeriodStatus::Calculated) {
                throw ValidationException::withMessages([
                    'status' => 'Charge period must be calculated before issuance.',
                ]);
            }
            if (! $period->calculation_source_hash
                || ! hash_equals($period->calculation_source_hash, $this->sourceFingerprint($period))) {
                throw ValidationException::withMessages([
                    'calculation' => 'اطلاعات مبنای محاسبه تغییر کرده یا محاسبه معتبر نیست؛ ابتدا بازبینی و محاسبه دوره را تکرار کنید.',
                ]);
            }

            $calculations = ChargeCalculation::query()
                ->where('charge_period_id', $period->getKey())
                ->orderBy('unit_id')
                ->get()
                ->groupBy('unit_id');

            $expenseAllocations = ChargeExpenseAllocation::query()
                ->where('charge_period_id', $period->getKey())
                ->with('buildingExpense:id,title,financial_category_id')
                ->orderBy('unit_id')
                ->orderBy('id')
                ->get();

            if ($calculations->isEmpty() && $expenseAllocations->isEmpty()) {
                throw ValidationException::withMessages([
                    'calculations' => 'هیچ محاسبه شارژ یا سهم هزینه‌ای برای این دوره وجود ندارد.',
                ]);
            }

            $invoiceGroups = [];

            foreach ($calculations as $unitId => $unitCalculations) {
                $key = $this->invoiceGroupKey(
                    (int) $unitId,
                    ExpensePayerResponsibility::Unit,
                    null
                );

                $invoiceGroups[$key] = [
                    'unit_id' => (int) $unitId,
                    'payer_responsibility' => ExpensePayerResponsibility::Unit,
                    'payer_user_id' => null,
                    'items' => [],
                ];

                foreach ($unitCalculations as $calculation) {
                    foreach (
                        $calculation->calculation_snapshot['items'] ?? []
                        as $snapshotItem
                    ) {
                        $amount = (int) ($snapshotItem['amount'] ?? 0);

                        if ($amount <= 0) {
                            continue;
                        }

                        $invoiceGroups[$key]['items'][] = [
                            'charge_item_id' => $snapshotItem['charge_item_id'] ?? null,
                            'title' => $snapshotItem['title'] ?? 'Charge',
                            'description' => null,
                            'quantity' => 1,
                            'unit_amount' => $amount,
                            'metadata' => [
                                'charge_period_id' => $period->getKey(),
                                'charge_formula_id' => $calculation->charge_formula_id,
                                'base_value' => $calculation->base_value,
                            ],
                        ];
                    }
                }
            }

            foreach ($expenseAllocations as $allocation) {
                if ((int) $allocation->allocated_amount <= 0) {
                    continue;
                }

                $responsibility = $allocation->payer_responsibility
                    instanceof ExpensePayerResponsibility
                        ? $allocation->payer_responsibility
                        : ExpensePayerResponsibility::from(
                            (string) $allocation->payer_responsibility
                        );
                $payerUserId = $allocation->payer_user_id
                    ? (int) $allocation->payer_user_id
                    : null;
                $key = $this->invoiceGroupKey(
                    (int) $allocation->unit_id,
                    $responsibility,
                    $payerUserId
                );

                $invoiceGroups[$key] ??= [
                    'unit_id' => (int) $allocation->unit_id,
                    'payer_responsibility' => $responsibility,
                    'payer_user_id' => $payerUserId,
                    'items' => [],
                ];

                $invoiceGroups[$key]['items'][] = [
                    'charge_item_id' => null,
                    'title' => $allocation->buildingExpense?->title
                        ?? 'هزینه عمومی ساختمان',
                    'description' => null,
                    'quantity' => 1,
                    'unit_amount' => (int) $allocation->allocated_amount,
                    'metadata' => [
                        ...($allocation->calculation_snapshot ?? []),
                        'charge_expense_allocation_id' => $allocation->getKey(),
                    ],
                ];
            }

            foreach ($invoiceGroups as $group) {
                if ($group['items'] === []) {
                    continue;
                }

                $invoice = UnitInvoice::query()->firstOrNew([
                    'building_id' => $period->building_id,
                    'unit_id' => $group['unit_id'],
                    'charge_period_id' => $period->getKey(),
                    'payer_responsibility' => $group['payer_responsibility']->value,
                    'payer_user_id' => $group['payer_user_id'],
                ]);

                if (
                    $invoice->exists
                    && $invoice->status !== InvoiceStatus::Draft
                ) {
                    continue;
                }

                $invoice->fill([
                    'invoice_number' => $invoice->invoice_number ?: $this->invoices->periodInvoiceNumber(
                        $period->building_id,
                        $period->getKey(),
                        $group['unit_id'],
                        $group['payer_responsibility'],
                        $group['payer_user_id']
                    ),
                    'issue_date' => now()->toDateString(),
                    'due_date' => $period->due_date,
                    'period_start' => $period->period_start,
                    'period_end' => $period->period_end,
                    'payer_responsibility' => $group['payer_responsibility'],
                    'payer_user_id' => $group['payer_user_id'],
                    'discount_amount' => 0,
                    'penalty_amount' => 0,
                    'paid_amount' => 0,
                    'status' => InvoiceStatus::Draft,
                    'description' => $period->title,
                    'created_by' => $actor->getKey(),
                ]);

                $invoice->save();

                $this->invoices->replaceItems($invoice, $group['items']);
                $this->invoices->recalculate($invoice);
                $this->invoices->issue($invoice);
            }

            $period->update([
                'status' => ChargePeriodStatus::Issued,
            ]);
        }, 3);

        return $period->refresh();
    }

    private function invoiceGroupKey(
        int $unitId,
        ExpensePayerResponsibility $responsibility,
        ?int $payerUserId
    ): string {
        return implode(':', [
            $unitId,
            $responsibility->value,
            $payerUserId ?? 'unit',
        ]);
    }
}
