<?php

namespace App\Console\Commands;

use App\Enums\InstallmentStatus;
use App\Models\InvoiceInstallment;
use Illuminate\Console\Command;

class MarkOverdueInvoiceInstallments extends Command
{
    protected $signature = 'invoices:mark-overdue-installments';

    protected $description = 'Mark unpaid invoice installments past their due date as overdue';

    public function handle(): int
    {
        $count = InvoiceInstallment::query()
            ->whereIn('status', [
                InstallmentStatus::Pending->value,
                InstallmentStatus::Partial->value,
            ])
            ->whereDate('due_date', '<', today())
            ->update([
                'status' => InstallmentStatus::Overdue->value,
            ]);

        $this->info("Marked {$count} installment(s) as overdue.");

        return self::SUCCESS;
    }
}
