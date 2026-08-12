<?php

namespace App\Listeners;

use App\Data\Notifications\NotificationMessage;
use App\Events\FacilityReservationCreated;
use App\Listeners\Concerns\QueuesUserNotifications;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

class NotifyReservationCreated implements ShouldQueue
{
    use Queueable, QueuesUserNotifications;

    public function handle(FacilityReservationCreated $event): void
    {
        $reservation = $event->reservation->loadMissing('user', 'buildingFacility');

        if (! $reservation->user) {
            return;
        }

        $this->queueForUsers(
            new Collection([$reservation->user]),
            new NotificationMessage(
                type: 'reservation.created',
                title: 'ثبت رزرو',
                message: "رزرو {$reservation->buildingFacility?->title} برای تاریخ {$reservation->reservation_date->toDateString()} ثبت شد.",
                data: ['reservation_id' => $reservation->id],
            ),
            "reservation-created:{$reservation->id}",
        );
    }
}
