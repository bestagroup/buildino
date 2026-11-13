<?php

namespace App\Services;

use App\Enums\ExpensePayerResponsibility;
use App\Enums\InvoiceStatus;
use App\Events\InvoiceIssued;
use App\Models\Unit;
use App\Models\UnitInvoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function createManual(
        Unit $unit,
        User $actor,
        array $data
    ): UnitInvoice {
        $unit->loadMissing('floor.block.building');
        $building = $unit->floor?->block?->building;

        if (! $building) {
            throw ValidationException::withMessages([
                'unit_id' => 'The unit is not connected to a building.',
            ]);
        }

        return DB::transaction(function () use (
            $unit,
            $building,
            $actor,
            $data
        ): UnitInvoice {
            $schedules = app(InvoicePeriodScheduleService::class);
            $schedules->lockBuilding($building->id);
            $periods = isset($data['start_charge_period_id']) ? $schedules->periods($building->id, (int) $data['start_charge_period_id'], (int) $data['period_installment_count']) : null;
            if ($periods) {
                $data = $this->scheduledData($data, $periods);
            }
            $invoice = UnitInvoice::query()->create([
                'start_charge_period_id' => $data['start_charge_period_id'] ?? null,
                'period_installment_count' => $data['period_installment_count'] ?? null,
                'building_id' => $building->getKey(),
                'unit_id' => $unit->getKey(),
                'charge_period_id' => null,
                'invoice_number' => sprintf(
                    'INV-%d-%s',
                    $building->getKey(),
                    strtoupper(Str::random(12))
                ),
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'period_start' => $data['period_start'] ?? null,
                'period_end' => $data['period_end'] ?? null,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'penalty_amount' => $data['penalty_amount'] ?? 0,
                'status' => InvoiceStatus::Draft,
                'description' => $data['description'] ?? null,
                'created_by' => $actor->getKey(),
            ]);

            $this->replaceItems($invoice, $data['items']);

            $invoice = $this->recalculate($invoice);
            if ($periods) $schedules->replace($invoice, $periods);

            return $invoice;
        });
    }

    public function updateDraft(
        UnitInvoice $invoice,
        array $data
    ): UnitInvoice {
        return DB::transaction(function () use ($invoice, $data): UnitInvoice {
            app(InvoicePeriodScheduleService::class)->lockBuilding($invoice->building_id);
            Unit::whereKey($invoice->unit_id)->lockForUpdate()->firstOrFail();
            $invoice = UnitInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->status !== InvoiceStatus::Draft) {
                throw ValidationException::withMessages(['status' => 'Only draft invoices can be edited.']);
            }
            $schedules = app(InvoicePeriodScheduleService::class);
            $schedules->assertEditable($invoice);
            $startId = $data['start_charge_period_id'] ?? $invoice->start_charge_period_id;
            $periods = null;
            if ($startId) {
                if ($invoice->charge_period_id || $invoice->paid_amount || $invoice->adjustment_amount || $invoice->credit_applied_amount || $invoice->discount_amount || $invoice->penalty_amount) {
                    throw ValidationException::withMessages(['invoice' => 'صورتحساب دوره یا سند دارای پرداخت، تعدیل، تخفیف یا جریمه قابل تبدیل به برنامه بدهی نیست.']);
                }
                $count = (int) ($data['period_installment_count'] ?? $invoice->period_installment_count ?? 1);
                $periods = $schedules->periods($invoice->building_id, (int) $startId, $count);
                $data = $this->scheduledData([...$data, 'start_charge_period_id' => $startId, 'period_installment_count' => $count], $periods);
            }
            $invoice->update(
                array_intersect_key(
                    $data,
                    array_flip([
                        'start_charge_period_id','period_installment_count','issue_date','due_date','period_start','period_end',
                        'discount_amount','penalty_amount','description',
                    ])
                )
            );

            if (array_key_exists('items', $data)) {
                $this->replaceItems($invoice, $data['items']);
            }

            $invoice = $this->recalculate($invoice);
            if ($periods) $schedules->replace($invoice, $periods);

            return $invoice;
        });
    }

    private function scheduledData(array $data, \Illuminate\Support\Collection $periods): array
    {
        return [...$data, 'issue_date' => today()->toDateString(), 'due_date' => $periods->first()->due_date,
            'period_start' => $periods->first()->period_start, 'period_end' => $periods->last()->period_end,
            'discount_amount' => 0, 'penalty_amount' => 0];
    }

    public function recalculate(UnitInvoice $invoice): UnitInvoice
    {
        return DB::transaction(function () use ($invoice): UnitInvoice {
            $invoice->refresh();

            $subtotal = (int) $invoice->invoiceItems()->sum('total_amount');
            $total = $subtotal - (int) $invoice->discount_amount + (int) $invoice->penalty_amount
                + (int) $invoice->adjustment_amount - (int) $invoice->credit_applied_amount;
            $paid = (int) $invoice->paid_amount;
            if (($subtotal >= 0 && $total < $paid) || abs($total) > 9007199254740991
                || ($subtotal < 0 && ($paid > 0 || $invoice->discount_amount || $invoice->penalty_amount || $invoice->adjustment_amount))) {
                throw ValidationException::withMessages(['total_amount' => 'مبلغ نهایی نامعتبر است؛ سند بستانکاری مستقل نباید پرداخت، تخفیف یا جریمه داشته باشد.']);
            }

            $status = $invoice->status;

            if (! in_array(
                $status,
                [
                    InvoiceStatus::Draft,
                    InvoiceStatus::Cancelled,
                    InvoiceStatus::Void,
                ],
                true
            )) {
                $status = $total < 0
                    ? ($invoice->credit_remaining_amount > 0 ? InvoiceStatus::Issued : InvoiceStatus::Paid)
                    : ($paid >= $total
                    ? InvoiceStatus::Paid
                    : (
                        $paid > 0
                            ? InvoiceStatus::Partial
                            : (
                                $invoice->due_date?->isPast()
                                    ? InvoiceStatus::Overdue
                                    : InvoiceStatus::Issued
                            )
                    ));
            }

            $invoice->update([
                'subtotal' => $subtotal,
                'total_amount' => $total,
                'paid_amount' => $paid,
                'outstanding_amount' => max(0, $total - $paid),
                'status' => $status,
            ]);

            return $invoice->refresh();
        });
    }

    public function issue(UnitInvoice $invoice): UnitInvoice
    {
        DB::transaction(function () use ($invoice): void {
            app(InvoicePeriodScheduleService::class)->lockBuilding($invoice->building_id);
            Unit::whereKey($invoice->unit_id)->lockForUpdate()->firstOrFail();
            $invoice = UnitInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->start_charge_period_id) {
                throw ValidationException::withMessages(['invoice' => 'این بدهی فقط از طریق محاسبه و صدور دوره‌های انتخاب‌شده اعمال می‌شود.']);
            }
            if ($invoice->status !== InvoiceStatus::Draft) {
                throw ValidationException::withMessages(['status' => 'Only draft invoices can be issued.']);
            }
            $invoice = $this->recalculate($invoice);
            if ($invoice->total_amount === 0 && ! $invoice->charge_period_id) {
                throw ValidationException::withMessages(['total_amount' => 'مبلغ نهایی صورتحساب نباید صفر باشد.']);
            }
            $invoice->forceFill([
                'status' => $invoice->total_amount === 0 ? InvoiceStatus::Paid : InvoiceStatus::Issued,
                'outstanding_amount' => max(0, $invoice->total_amount),
                'credit_remaining_amount' => max(0, -$invoice->total_amount),
            ])->save();
            app(InvoiceCreditService::class)->apply($invoice->unit_id, $invoice->building_id);
            DB::afterCommit(fn () => InvoiceIssued::dispatch($invoice->fresh()));
        }, 3);

        return $invoice->refresh();
    }

    public function voidDraft(UnitInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            app(InvoicePeriodScheduleService::class)->lockBuilding($invoice->building_id);
            Unit::whereKey($invoice->unit_id)->lockForUpdate()->firstOrFail();
            $invoice = UnitInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->status !== InvoiceStatus::Draft) {
                throw ValidationException::withMessages(['status' => 'Only draft invoices can be deleted.']);
            }
            app(InvoicePeriodScheduleService::class)->assertEditable($invoice);
            \App\Models\ChargePeriod::whereIn('id', $invoice->periodAllocations()->pluck('charge_period_id'))->where('status', 'calculated')
                ->update(['status' => 'draft', 'calculation_source_hash' => null]);
            $invoice->periodAllocations()->delete();
            $invoice->delete();
        }, 3);
    }

    public function replaceItems(UnitInvoice $invoice, array $items): void
    {
        $invoice->invoiceItems()->delete();

        foreach ($items as $item) {
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $unitAmount = (int) $item['unit_amount'];
            if (abs($unitAmount) > 9007199254740991 || abs($quantity * $unitAmount) > 9007199254740991) {
                throw ValidationException::withMessages(['items' => 'مبلغ ردیف خارج از محدوده مجاز است.']);
            }

            $invoice->invoiceItems()->create([
                'charge_item_id' => $item['charge_item_id'] ?? null,
                'title' => $item['title'],
                'description' => $item['description'] ?? null,
                'quantity' => $quantity,
                'unit_amount' => $unitAmount,
                'total_amount' => $quantity * $unitAmount,
                'metadata' => $item['metadata'] ?? null,
            ]);
        }
    }

    public function periodInvoiceNumber(
        int $buildingId,
        int $periodId,
        int $unitId,
        ExpensePayerResponsibility $responsibility = ExpensePayerResponsibility::Unit,
        ?int $payerUserId = null
    ): string {
        $base = sprintf(
            'INV-%d-%d-%d',
            $buildingId,
            $periodId,
            $unitId
        );

        if ($responsibility === ExpensePayerResponsibility::Unit) {
            return $base;
        }

        return sprintf(
            '%s-%s-%d',
            $base,
            strtoupper($responsibility->value),
            $payerUserId
        );
    }
}
