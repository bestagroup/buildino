<?php

namespace App\Jobs\Notifications;

use App\Data\Notifications\NotificationMessage;
use App\Models\User;
use App\Services\Notifications\UserNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendUserNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 30;
    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $userId,
        public readonly NotificationMessage $notification,
        public readonly string $channel,
        public readonly string $idempotencyKey,
    ) {
        $this->onQueue(config('notifications.queue', 'notifications'));
    }

    public function backoff(): array
    {
        return [10, 30, 120, 300];
    }

    public function handle(UserNotificationService $service): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $service->send(
            $user,
            $this->notification,
            $this->channel,
            $this->idempotencyKey,
        );
    }

    public function tags(): array
    {
        return [
            'notification',
            'user:'.$this->userId,
            'type:'.$this->notification->type,
            'channel:'.$this->channel,
        ];
    }
}
