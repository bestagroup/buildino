<?php

namespace App\Services;

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
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only draft invoices can be edited.',
            ]);
        }

        return DB::transaction(function () use ($invoice, $data): UnitInvoice {
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
            $total = max(
                0,
                $subtotal
                - (int) $invoice->discount_amount
                + (int) $invoice->penalty_amount
            );
            $paid = min((int) $invoice->paid_amount, $total);

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
                $status = $paid >= $total && $total > 0
                    ? InvoiceStatus::Paid
                    : (
                        $paid > 0
                            ? InvoiceStatus::Partial
                            : (
                                $invoice->due_date?->isPast()
                                    ? InvoiceStatus::Overdue
                                    : InvoiceStatus::Issued
                            )
                    );
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
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only draft invoices can be issued.',
            ]);
        }

        $invoice = $this->recalculate($invoice);

        if ($invoice->total_amount <= 0) {
            throw ValidationException::withMessages([
                'total_amount' => 'Invoice total amount must be greater than zero.',
            ]);
        }

        DB::transaction(function () use ($invoice): void {
            $invoice->update([
                'status' => InvoiceStatus::Issued,
                'outstanding_amount' => $invoice->total_amount,
            ]);

            DB::afterCommit(
                fn () => InvoiceIssued::dispatch($invoice->fresh())
            );
        });

        return $invoice->refresh();
    }

    public function voidDraft(UnitInvoice $invoice): void
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only draft invoices can be deleted.',
            ]);
        }

        $invoice->delete();
    }

    public function replaceItems(UnitInvoice $invoice, array $items): void
    {
        $invoice->invoiceItems()->delete();

        foreach ($items as $item) {
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $unitAmount = max(0, (int) $item['unit_amount']);

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
        int $unitId
    ): string {
        return sprintf(
            'INV-%d-%d-%d',
            $buildingId,
            $periodId,
            $unitId
        );
    }
}
