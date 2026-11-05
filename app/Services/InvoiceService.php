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
            $invoice = UnitInvoice::query()->create([
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

            return $this->recalculate($invoice);
        });
    }

    public function updateDraft(
        UnitInvoice $invoice,
        array $data
    ): UnitInvoice {
        return DB::transaction(function () use ($invoice, $data): UnitInvoice {
            Unit::whereKey($invoice->unit_id)->lockForUpdate()->firstOrFail();
            $invoice = UnitInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->status !== InvoiceStatus::Draft) {
                throw ValidationException::withMessages(['status' => 'Only draft invoices can be edited.']);
            }
            $invoice->update(
                array_intersect_key(
                    $data,
                    array_flip([
                        'issue_date','due_date','period_start','period_end',
                        'discount_amount','penalty_amount','description',
                    ])
                )
            );

            if (array_key_exists('items', $data)) {
                $this->replaceItems($invoice, $data['items']);
            }

            return $this->recalculate($invoice);
        });
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
            Unit::whereKey($invoice->unit_id)->lockForUpdate()->firstOrFail();
            $invoice = UnitInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->status !== InvoiceStatus::Draft) {
                throw ValidationException::withMessages(['status' => 'Only draft invoices can be issued.']);
            }
            $invoice = $this->recalculate($invoice);
            if ($invoice->total_amount === 0) {
                throw ValidationException::withMessages(['total_amount' => 'مبلغ نهایی صورتحساب نباید صفر باشد.']);
            }
            $invoice->forceFill([
                'status' => InvoiceStatus::Issued,
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
            Unit::whereKey($invoice->unit_id)->lockForUpdate()->firstOrFail();
            $invoice = UnitInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->status !== InvoiceStatus::Draft) {
                throw ValidationException::withMessages(['status' => 'Only draft invoices can be deleted.']);
            }
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
