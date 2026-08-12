<?php

namespace App\Listeners;

use App\Data\Notifications\NotificationMessage;
use App\Events\SupportTicketResolved;
use App\Listeners\Concerns\QueuesUserNotifications;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

class NotifySupportTicketResolved implements ShouldQueue
{
    use Queueable, QueuesUserNotifications;

    public function handle(SupportTicketResolved $event): void
    {
        $ticket = $event->ticket->loadMissing('user');

        if (! $ticket->user) {
            return;
        }

        $this->queueForUsers(
            new Collection([$ticket->user]),
            new NotificationMessage(
                type: 'support.resolved',
                title: 'حل تیکت پشتیبانی',
                message: "تیکت {$ticket->ticket_number} حل شد.",
                data: ['ticket_id' => $ticket->id],
            ),
            "support-resolved:{$ticket->id}",
        );
    }
}
