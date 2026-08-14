<?php

namespace App\Listeners;

use App\Data\Notifications\NotificationMessage;
use App\Events\InvoiceIssued;
use App\Listeners\Concerns\QueuesUserNotifications;
use App\Services\Notifications\NotificationRecipientResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyInvoiceIssued implements ShouldQueue
{
    use Queueable, QueuesUserNotifications;

    public function __construct(private readonly NotificationRecipientResolver $recipients) {}

    public function handle(InvoiceIssued $event): void
    {
        $invoice = $event->invoice;

        $this->queueForUsers(
            $this->recipients->forUnit($invoice->unit_id),
            new NotificationMessage(
                type: 'invoice.issued',
                title: 'صورتحساب جدید',
                message: "صورتحساب {$invoice->invoice_number} به مبلغ {$invoice->total_amount} صادر شد.",
                data: ['invoice_id' => $invoice->id],
            ),
            "invoice-issued:{$invoice->id}",
        );
    }
}
