<?php

namespace App\Services;

use App\Enums\InstallmentStatus;
use App\Enums\InvoiceStatus;
use App\Models\FinancialAuditLog;
use App\Models\InvoiceInstallment;
use App\Models\UnitInvoice;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class InvoiceInstallmentService
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return Collection<int, InvoiceInstallment>
     */
    public function replace(
        UnitInvoice $invoice,
        User $actor,
        array $items
    ): Collection {
        return DB::transaction(function () use (
            $actor,
            $invoice,
            $items
        ): Collection {
            $invoice = UnitInvoice::query()
                ->lockForUpdate()
                ->findOrFail($invoice->getKey());

            if (! in_array(
                $invoice->status,
                [InvoiceStatus::Issued, InvoiceStatus::Overdue],
                true
            )) {
                throw ValidationException::withMessages([
                    'invoice' => 'An installment plan can only be created for an issued or overdue invoice.',
                ]);
            }

            if ((int) $invoice->paid_amount !== 0) {
                throw ValidationException::withMessages([
                    'invoice' => 'An installment plan cannot be replaced after a payment has been applied.',
                ]);
            }

            $total = collect($items)->sum(
                fn (array $item): int => (int) $item['amount']
            );

            if ($total !== (int) $invoice->total_amount) {
                throw ValidationException::withMessages([
                    'installments' => 'The sum of installment amounts must exactly equal the invoice total.',
                ]);
            }

            $ordered = collect($items)
                ->sortBy('due_date')
                ->values();

            if (
                $ordered->pluck('due_date')->unique()->count()
                !== $ordered->count()
            ) {
                throw ValidationException::withMessages([
                    'installments' => 'Each installment must have a distinct due date.',
                ]);
            }

            $oldValues = $invoice->invoiceInstallments()
                ->orderBy('installment_number')
                ->get()
                ->map(fn (InvoiceInstallment $item): array => [
                    'number' => (int) $item->installment_number,
                    'due_date' => $item->due_date?->toDateString(),
                    'amount' => (int) $item->amount,
                    'status' => $item->status instanceof \BackedEnum
                        ? $item->status->value
                        : $item->status,
                ])
                ->all();

            $invoice->invoiceInstallments()->delete();

            foreach ($ordered as $index => $item) {
                $invoice->invoiceInstallments()->create([
                    'installment_number' => $index + 1,
                    'due_date' => $item['due_date'],
                    'amount' => (int) $item['amount'],
                    'paid_amount' => 0,
                    'status' => InstallmentStatus::Pending,
                    'metadata' => $item['metadata'] ?? null,
                ]);
            }

            $created = $invoice->invoiceInstallments()
                ->orderBy('installment_number')
                ->get();

            $this->audit(
                $invoice,
                $actor,
                'invoice.installment_plan.replaced',
                $oldValues,
                $created->map(fn (InvoiceInstallment $item): array => [
                    'number' => (int) $item->installment_number,
                    'due_date' => $item->due_date?->toDateString(),
                    'amount' => (int) $item->amount,
                ])->all()
            );

            return $created;
        }, 3);
    }

    public function cancel(
        UnitInvoice $invoice,
        User $actor
    ): void {
        DB::transaction(function () use ($actor, $invoice): void {
            $invoice = UnitInvoice::query()
                ->lockForUpdate()
                ->findOrFail($invoice->getKey());

            $installments = $invoice->invoiceInstallments()
                ->lockForUpdate()
                ->get();

            if ($installments->isEmpty()) {
                return;
            }

            if ($installments->contains(
                fn (InvoiceInstallment $item): bool => (int) $item->paid_amount > 0
            )) {
                throw ValidationException::withMessages([
                    'installments' => 'A plan with applied payments cannot be cancelled.',
                ]);
            }

            $invoice->invoiceInstallments()->update([
                'status' => InstallmentStatus::Cancelled->value,
            ]);

            $this->audit(
                $invoice,
                $actor,
                'invoice.installment_plan.cancelled',
                ['active_count' => $installments->count()],
                ['active_count' => 0]
            );
        }, 3);
    }

    public function applyPayment(
        UnitInvoice $invoice,
        int $amount
    ): void {
        $installments = $invoice->invoiceInstallments()
            ->where('status', '!=', InstallmentStatus::Cancelled->value)
            ->orderBy('due_date')
            ->orderBy('installment_number')
            ->lockForUpdate()
            ->get();

        if ($installments->isEmpty()) {
            return;
        }

        $remaining = $amount;

        foreach ($installments as $installment) {
            if ($remaining <= 0) {
                break;
            }

            $due = max(
                0,
                (int) $installment->amount
                + (int) $installment->penalty_amount
                - (int) $installment->waived_amount
                - (int) $installment->paid_amount
            );

            if ($due === 0) {
                continue;
            }

            $applied = min($remaining, $due);
            $paid = (int) $installment->paid_amount + $applied;
            $settled = $paid >= (
                (int) $installment->amount
                + (int) $installment->penalty_amount
                - (int) $installment->waived_amount
            );

            $installment->update([
                'paid_amount' => $paid,
                'status' => $settled
                    ? InstallmentStatus::Paid
                    : InstallmentStatus::Partial,
                'paid_at' => $settled ? now() : null,
            ]);

            $remaining -= $applied;
        }

        if ($remaining !== 0) {
            throw ValidationException::withMessages([
                'installments' => 'The installment plan is inconsistent with the invoice outstanding amount.',
            ]);
        }
    }

    private function audit(
        UnitInvoice $invoice,
        User $actor,
        string $action,
        array $oldValues,
        array $newValues
    ): void {
        FinancialAuditLog::query()->create([
            'request_id' => request()?->header('X-Request-ID') ?: (string) Str::uuid(),
            'user_id' => $actor->getKey(),
            'action' => $action,
            'entity_type' => $invoice->getMorphClass(),
            'entity_id' => $invoice->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()?->ip(),
            'user_agent' => mb_substr((string) request()?->userAgent(), 0, 1000),
            'occurred_at' => now(),
        ]);
    }
}
