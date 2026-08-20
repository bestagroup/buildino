<?php

namespace App\Services;

use App\Enums\InstallmentStatus;
use App\Enums\InvoiceStatus;
use App\Models\FinancialAuditLog;
use App\Models\UnitInvoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class InvoicePenaltyService
{
    public function adjust(
        UnitInvoice $invoice,
        User $actor,
        string $action,
        int $amount,
        string $reason
    ): UnitInvoice {
        return DB::transaction(function () use (
            $action,
            $actor,
            $amount,
            $invoice,
            $reason
        ): UnitInvoice {
            $invoice = UnitInvoice::query()
                ->lockForUpdate()
                ->findOrFail($invoice->getKey());

            if (! in_array(
                $invoice->status,
                [
                    InvoiceStatus::Issued,
                    InvoiceStatus::Partial,
                    InvoiceStatus::Overdue,
                ],
                true
            )) {
                throw ValidationException::withMessages([
                    'invoice' => 'Penalty adjustments are only allowed on open issued invoices.',
                ]);
            }

            $old = $this->snapshot($invoice);

            if ($action === 'add') {
                $this->add($invoice, $amount);
            } else {
                $this->waive($invoice, $amount);
            }

            $invoice->refresh();

            FinancialAuditLog::query()->create([
                'request_id' => request()?->header('X-Request-ID')
                    ?: (string) Str::uuid(),
                'user_id' => $actor->getKey(),
                'action' => "invoice.penalty.{$action}",
                'entity_type' => $invoice->getMorphClass(),
                'entity_id' => $invoice->getKey(),
                'old_values' => $old,
                'new_values' => [
                    ...$this->snapshot($invoice),
                    'adjustment_amount' => $amount,
                    'reason' => $reason,
                ],
                'ip_address' => request()?->ip(),
                'user_agent' => mb_substr(
                    (string) request()?->userAgent(),
                    0,
                    1000
                ),
                'occurred_at' => now(),
            ]);

            return $invoice;
        }, 3);
    }

    private function add(UnitInvoice $invoice, int $amount): void
    {
        $invoice->increment('penalty_amount', $amount);
        $invoice->increment('total_amount', $amount);
        $invoice->increment('outstanding_amount', $amount);

        $installment = $invoice->invoiceInstallments()
            ->where('status', '!=', InstallmentStatus::Cancelled->value)
            ->orderByDesc('due_date')
            ->orderByDesc('installment_number')
            ->lockForUpdate()
            ->first();

        $installment?->increment('penalty_amount', $amount);
    }

    private function waive(UnitInvoice $invoice, int $amount): void
    {
        $maxWaivable = min(
            (int) $invoice->penalty_amount,
            max(0, (int) $invoice->total_amount - (int) $invoice->paid_amount)
        );

        if ($amount > $maxWaivable) {
            throw ValidationException::withMessages([
                'amount' => 'The waiver exceeds the unpaid penalty amount.',
            ]);
        }

        $remaining = $amount;
        $installments = $invoice->invoiceInstallments()
            ->where('status', '!=', InstallmentStatus::Cancelled->value)
            ->orderByDesc('due_date')
            ->orderByDesc('installment_number')
            ->lockForUpdate()
            ->get();

        foreach ($installments as $installment) {
            if ($remaining === 0) {
                break;
            }

            $unpaid = max(
                0,
                (int) $installment->amount
                + (int) $installment->penalty_amount
                - (int) $installment->waived_amount
                - (int) $installment->paid_amount
            );
            $waived = min($remaining, $unpaid);

            if ($waived > 0) {
                $installment->increment('waived_amount', $waived);
                $remaining -= $waived;
            }
        }

        if ($installments->isNotEmpty() && $remaining !== 0) {
            throw ValidationException::withMessages([
                'installments' => 'The installment plan cannot absorb this penalty waiver.',
            ]);
        }

        $invoice->decrement('penalty_amount', $amount);
        $invoice->increment('waived_penalty_amount', $amount);
        $invoice->decrement('total_amount', $amount);
        $invoice->decrement('outstanding_amount', $amount);
    }

    private function snapshot(UnitInvoice $invoice): array
    {
        return [
            'penalty_amount' => (int) $invoice->penalty_amount,
            'waived_penalty_amount' => (int) $invoice->waived_penalty_amount,
            'total_amount' => (int) $invoice->total_amount,
            'paid_amount' => (int) $invoice->paid_amount,
            'outstanding_amount' => (int) $invoice->outstanding_amount,
        ];
    }
}
