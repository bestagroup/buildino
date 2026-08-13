<?php

namespace App\Services;

use App\Enums\ChargePeriodStatus;
use App\Enums\InvoiceStatus;
use App\Models\ChargeCalculation;
use App\Models\ChargeFormula;
use App\Models\ChargePeriod;
use App\Models\Unit;
use App\Models\UnitInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChargePeriodService
{
    public function __construct(
        private readonly ChargeService $charges,
        private readonly InvoiceService $invoices
    ) {}

    public function calculate(ChargePeriod $period): ChargePeriod
    {
        if (! in_array(
            $period->status,
            [
                ChargePeriodStatus::Draft,
                ChargePeriodStatus::Calculated,
            ],
            true
        )) {
            throw ValidationException::withMessages([
                'status' => 'Only draft or calculated charge periods can be recalculated.',
            ]);
        }

        $period->update([
            'status' => ChargePeriodStatus::Calculating,
        ]);

        try {
            DB::transaction(function () use ($period): void {
                $formulas = ChargeFormula::query()
                    ->where('building_id', $period->building_id)
                    ->where('is_active', true)
                    ->with('chargeItems')
                    ->get();

                if ($formulas->isEmpty()) {
                    throw ValidationException::withMessages([
                        'formulas' => 'No active charge formula exists for this building.',
                    ]);
                }

                $units = Unit::query()
                    ->where('is_active', true)
                    ->whereHas(
                        'floor.block',
                        fn (Builder $query) => $query->where(
                            'building_id',
                            $period->building_id
                        )
                    )
                    ->get();

                if ($units->isEmpty()) {
                    throw ValidationException::withMessages([
                        'units' => 'No active unit exists for this building.',
                    ]);
                }

                ChargeCalculation::query()
                    ->where('charge_period_id', $period->getKey())
                    ->delete();

                foreach ($units as $unit) {
                    foreach ($formulas as $formula) {
                        $result = $this->charges->calculateBreakdown(
                            $formula,
                            $unit,
                            $period->period_start,
                            $period->period_end
                        );

                        ChargeCalculation::query()->create([
                            'charge_period_id' => $period->getKey(),
                            'unit_id' => $unit->getKey(),
                            'charge_formula_id' => $formula->getKey(),
                            'base_value' => $result['base_value'],
                            'calculated_amount' => $result['amount'],
                            'calculation_snapshot' => $result['snapshot'],
                        ]);
                    }
                }

                $period->update([
                    'status' => ChargePeriodStatus::Calculated,
                ]);
            });
        } catch (\Throwable $e) {
            $period->update([
                'status' => ChargePeriodStatus::Draft,
            ]);

            throw $e;
        }

        return $period->refresh();
    }

    public function issue(
        ChargePeriod $period,
        User $actor
    ): ChargePeriod {
        if ($period->status !== ChargePeriodStatus::Calculated) {
            throw ValidationException::withMessages([
                'status' => 'Charge period must be calculated before issuance.',
            ]);
        }

        DB::transaction(function () use ($period, $actor): void {
            $period = ChargePeriod::query()
                ->lockForUpdate()
                ->findOrFail($period->getKey());

            $calculations = ChargeCalculation::query()
                ->where('charge_period_id', $period->getKey())
                ->orderBy('unit_id')
                ->get()
                ->groupBy('unit_id');

            if ($calculations->isEmpty()) {
                throw ValidationException::withMessages([
                    'calculations' => 'No charge calculations exist for this period.',
                ]);
            }

            foreach ($calculations as $unitId => $unitCalculations) {
                $items = [];

                foreach ($unitCalculations as $calculation) {
                    foreach (
                        $calculation->calculation_snapshot['items'] ?? []
                        as $snapshotItem
                    ) {
                        $amount = (int) ($snapshotItem['amount'] ?? 0);

                        if ($amount <= 0) {
                            continue;
                        }

                        $items[] = [
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

                if ($items === []) {
                    continue;
                }

                $invoice = UnitInvoice::query()->firstOrNew([
                    'building_id' => $period->building_id,
                    'unit_id' => (int) $unitId,
                    'charge_period_id' => $period->getKey(),
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
                        (int) $unitId
                    ),
                    'issue_date' => now()->toDateString(),
                    'due_date' => $period->due_date,
                    'period_start' => $period->period_start,
                    'period_end' => $period->period_end,
                    'discount_amount' => 0,
                    'penalty_amount' => 0,
                    'paid_amount' => 0,
                    'status' => InvoiceStatus::Draft,
                    'description' => $period->title,
                    'created_by' => $actor->getKey(),
                ]);

                $invoice->save();

                $this->invoices->replaceItems($invoice, $items);
                $this->invoices->recalculate($invoice);
                $this->invoices->issue($invoice);
            }

            $period->update([
                'status' => ChargePeriodStatus::Issued,
            ]);
        });

        return $period->refresh();
    }
}
