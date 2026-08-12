<?php

namespace App\Console\Commands;

use App\Data\Notifications\NotificationMessage;
use App\Jobs\Notifications\SendUserNotificationJob;
use App\Models\UnitInvoice;
use App\Services\Notifications\NotificationRecipientResolver;
use Illuminate\Console\Command;

class SendInvoiceDueReminders extends Command
{
    protected $signature = 'notifications:invoice-reminders {--days=1}';
    protected $description = 'Queue reminders for invoices nearing their due date.';

    public function handle(NotificationRecipientResolver $recipients): int
    {
        $days = max(0, (int) $this->option('days'));
        $date = now()->addDays($days)->toDateString();

        UnitInvoice::query()
            ->whereDate('due_date', $date)
            ->where('outstanding_amount', '>', 0)
            ->whereNotIn('status', ['paid', 'cancelled', 'void'])
            ->chunkById(200, function ($invoices) use ($recipients, $date): void {
                foreach ($invoices as $invoice) {
                    foreach ($recipients->forUnit($invoice->unit_id) as $user) {
                        foreach (['database', 'sms', 'email', 'push'] as $channel) {
                            SendUserNotificationJob::dispatch(
                                $user->id,
                                new NotificationMessage(
                                    'invoice.due_reminder',
                                    'یادآوری سررسید صورتحساب',
                                    "صورتحساب {$invoice->invoice_number} در تاریخ {$invoice->due_date->toDateString()} سررسید می‌شود.",
                                    ['invoice_id' => $invoice->id],
                                ),
                                $channel,
                                "invoice-reminder:{$invoice->id}:{$date}:{$user->id}:{$channel}",
                            );
                        }
                    }
                }
            });

        return self::SUCCESS;
    }
}
