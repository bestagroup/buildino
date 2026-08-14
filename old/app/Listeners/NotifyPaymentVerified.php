<?php

namespace App\Listeners;

use App\Data\Notifications\NotificationMessage;
use App\Events\PaymentVerified;
use App\Listeners\Concerns\QueuesUserNotifications;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

class NotifyPaymentVerified implements ShouldQueue
{
    use Queueable, QueuesUserNotifications;

    public function handle(PaymentVerified $event): void
    {
        $payment = $event->payment->loadMissing('payerUser');

        if (! $payment->payerUser) {
            return;
        }

        $this->queueForUsers(
            new Collection([$payment->payerUser]),
            new NotificationMessage(
                type: 'payment.verified',
                title: 'تأیید پرداخت',
                message: "پرداخت {$payment->payment_number} به مبلغ {$payment->amount} با موفقیت تأیید شد.",
                data: ['payment_id' => $payment->id],
            ),
            "payment-verified:{$payment->id}",
        );
    }
}
