<?php

namespace App\Listeners\Concerns;

use App\Data\Notifications\NotificationMessage;
use App\Enums\NotificationChannel;
use App\Jobs\Notifications\SendUserNotificationJob;
use App\Models\User;
use Illuminate\Support\Collection;

trait QueuesUserNotifications
{
    protected function queueForUsers(
        Collection $users,
        NotificationMessage $message,
        string $keyPrefix,
        array $channels = [
            NotificationChannel::Database->value,
            NotificationChannel::Sms->value,
            NotificationChannel::Email->value,
            NotificationChannel::Push->value,
        ],
    ): void {
        $users->unique('id')->each(function (User $user) use ($message, $keyPrefix, $channels): void {
            foreach ($channels as $channel) {
                SendUserNotificationJob::dispatch(
                    userId: $user->id,
                    notification: $message,
                    channel: $channel,
                    idempotencyKey: "{$keyPrefix}:user:{$user->id}:channel:{$channel}",
                )->afterCommit();
            }
        });
    }
}
