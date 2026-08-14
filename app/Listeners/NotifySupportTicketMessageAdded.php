<?php

namespace App\Listeners;

use App\Data\Notifications\NotificationMessage;
use App\Events\SupportTicketMessageAdded;
use App\Listeners\Concerns\QueuesUserNotifications;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

class NotifySupportTicketMessageAdded implements ShouldQueue
{
    use Queueable, QueuesUserNotifications;

    public function handle(SupportTicketMessageAdded $event): void
    {
        $message = $event->message->loadMissing(
            'supportTicket.user',
            'supportTicket.assignedTo',
            'user'
        );

        $ticket = $message->supportTicket;

        if (! $ticket || $message->is_internal) {
            return;
        }

        $recipient = (int) $message->user_id === (int) $ticket->user_id
            ? $ticket->assignedTo
            : $ticket->user;

        if (! $recipient) {
            return;
        }

        $this->queueForUsers(
            new Collection([$recipient]),
            new NotificationMessage(
                type: 'support.message',
                title: 'پیام جدید پشتیبانی',
                message: "برای تیکت {$ticket->ticket_number} پیام جدیدی ثبت شد.",
                data: [
                    'ticket_id' => $ticket->id,
                    'message_id' => $message->id,
                ],
            ),
            "support-message:{$message->id}"
        );
    }
}
