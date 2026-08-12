<?php

namespace App\Listeners;

use App\Data\Notifications\NotificationMessage;
use App\Events\SupportTicketAssigned;
use App\Listeners\Concerns\QueuesUserNotifications;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

class NotifySupportTicketAssigned implements ShouldQueue
{
    use Queueable, QueuesUserNotifications;

    public function handle(SupportTicketAssigned $event): void
    {
        $ticket = $event->ticket->loadMissing('assignedTo');

        if (! $ticket->assignedTo) {
            return;
        }

        $this->queueForUsers(
            new Collection([$ticket->assignedTo]),
            new NotificationMessage(
                type: 'support.assigned',
                title: 'تیکت جدید',
                message: "تیکت {$ticket->ticket_number} به شما ارجاع شد.",
                data: ['ticket_id' => $ticket->id],
            ),
            "support-assigned:{$ticket->id}",
        );
    }
}
