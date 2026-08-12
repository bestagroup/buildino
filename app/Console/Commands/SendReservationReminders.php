<?php

namespace App\Console\Commands;

use App\Data\Notifications\NotificationMessage;
use App\Jobs\Notifications\SendUserNotificationJob;
use App\Models\FacilityReservation;
use Illuminate\Console\Command;

class SendReservationReminders extends Command
{
    protected $signature = 'notifications:reservation-reminders {--date=}';
    protected $description = 'Queue reminders for upcoming approved/confirmed facility reservations.';

    public function handle(): int
    {
        $date = $this->option('date') ?: now()->addDay()->toDateString();

        FacilityReservation::query()
            ->whereDate('reservation_date', $date)
            ->whereIn('status', ['approved', 'confirmed'])
            ->chunkById(200, function ($reservations) use ($date): void {
                foreach ($reservations as $reservation) {
                    foreach (['database', 'sms', 'email', 'push'] as $channel) {
                        SendUserNotificationJob::dispatch(
                            $reservation->user_id,
                            new NotificationMessage(
                                'reservation.reminder',
                                'یادآوری رزرو',
                                "رزرو شما برای تاریخ {$reservation->reservation_date->toDateString()} از ساعت {$reservation->start_time} است.",
                                ['reservation_id' => $reservation->id],
                            ),
                            $channel,
                            "reservation-reminder:{$reservation->id}:{$date}:{$channel}",
                        );
                    }
                }
            });

        return self::SUCCESS;
    }
}
