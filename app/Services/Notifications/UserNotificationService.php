<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\PushSender;
use App\Contracts\Notifications\SmsSender;
use App\Data\Notifications\NotificationMessage;
use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Models\NotificationLog;
use App\Models\UserDevice;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Throwable;

class UserNotificationService
{
    private function canSend(User $user, string $channel): bool
    {
        return match ($channel) {
            NotificationChannel::Database->value => true,

            NotificationChannel::Sms->value =>
            filled($user->mobile),

            NotificationChannel::Email->value =>
            filled($user->email),

            NotificationChannel::Push->value =>
            $user->userDevices()
                ->whereNotNull('push_token')
                ->exists(),

            default => false,
        };
    }
    public function __construct(
        private readonly NotificationPreferenceService $preferences,
        private readonly SmsSender $sms,
        private readonly PushSender $push,
    ) {}

    public function send(
        User $user,
        NotificationMessage $notification,
        string $channel,
        string $idempotencyKey,
    ): ?NotificationLog {

        if (! $user->is_active || $user->is_blocked) {
            return null;
        }
        if (! $this->preferences->enabled($user, $notification->type, $channel)) {
            return null;
        }
        if (! $this->canSend($user, $channel)) {
            return null;
        }

        $log = NotificationLog::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'notifiable_type' => $user->getMorphClass(),
                'notifiable_id' => $user->getKey(),
                'notification_type' => $notification->type,
                'channel' => $channel,
                'provider' => $this->providerName($channel),
                'title' => $notification->title,
                'message' => $notification->message,
                'status' => NotificationStatus::Queued,
                'attempts' => 0,
                'response' => ['data' => $notification->data],
            ],
        );

        if (! $log->wasRecentlyCreated && in_array(
            $log->status,
            [NotificationStatus::Sent, NotificationStatus::Delivered],
            true
        )) {
            return $log;
        }

        $log->forceFill([
            'status' => NotificationStatus::Processing,
            'attempts' => $log->attempts + 1,
            'last_attempt_at' => now(),
        ])->save();

        try {
            $response = match ($channel) {
                NotificationChannel::Database->value => ['stored' => true],
                NotificationChannel::Sms->value => $this->sendSms($user, $notification),
                NotificationChannel::Email->value => $this->sendEmail($user, $notification),
                NotificationChannel::Push->value => $this->sendPush($user, $notification),
                default => throw new \InvalidArgumentException("Unsupported channel [$channel]."),
            };

            $log->forceFill([
                'status' => NotificationStatus::Sent,
                'provider_message_id' =>
                    $this->providerMessageId(
                        $response
                    ),
                'sent_at' => now(),
                'failure_reason' => null,
                'failed_at' => null,
                'response' => array_merge(
                    $log->response ?? [],
                    [
                        'provider_response' =>
                            $response,
                    ]
                ),
            ])->save();

            return $log->refresh();
        } catch (Throwable $e) {
            $log->forceFill([
                'status' => NotificationStatus::Failed,
                'failed_at' => now(),
                'failure_reason' => mb_substr($e->getMessage(), 0, 2000),
            ])->save();

            throw $e;
        }
    }

    private function sendSms(User $user, NotificationMessage $notification): array
    {
        if (! $user->mobile) {
            throw new \RuntimeException('User has no mobile number.');
        }

        return $this->sms->send($user->mobile, $notification->message);
    }

    private function sendEmail(User $user, NotificationMessage $notification): array
    {
        if (! $user->email) {
            throw new \RuntimeException('User has no email address.');
        }

        Mail::raw(
            $notification->message,
            fn ($mail) => $mail->to($user->email)->subject($notification->title),
        );

        return ['accepted' => true, 'recipient' => $user->email];
    }

    private function sendPush(User $user, NotificationMessage $notification): array
    {
        $tokens = $user->userDevices()
            ->whereNotNull('push_token')
            ->pluck('push_token')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($tokens === []) {
            throw new \RuntimeException('User has no push token.');
        }

        $response = $this->push->send(
            $tokens,
            $notification->title,
            $notification->message,
            $notification->data,
        );

        $invalidTokens =
            collect(
                $response['invalid_tokens']
                ?? []
            )
                ->filter(
                    fn ($token): bool =>
                        is_string($token)
                        && $token !== ''
                )
                ->unique()
                ->values();

        if ($invalidTokens->isNotEmpty()) {
            UserDevice::query()
                ->whereIn(
                    'push_token',
                    $invalidTokens->all()
                )
                ->update([
                    'push_token' => null,
                ]);
        }

        /*
         * Never persist raw device tokens in notification_logs.
         * FCM sender already exposes token hashes in its diagnostics.
         */
        unset(
            $response['invalid_tokens']
        );

        if (
            array_key_exists(
                'accepted',
                $response
            )
            && ! $response['accepted']
        ) {
            throw new \RuntimeException(
                'Push provider rejected all target devices.'
            );
        }

        return $response;
    }

    private function providerMessageId(
        array $response
    ): ?string {
        foreach (
            [
                'provider_message_id',
                'message_id',
                'id',
                'name',
            ]
            as $key
        ) {
            $value =
                data_get(
                    $response,
                    $key
                );

            if (
                is_scalar($value)
                && trim(
                    (string) $value
                ) !== ''
            ) {
                return mb_substr(
                    trim(
                        (string) $value
                    ),
                    0,
                    255
                );
            }
        }

        return null;
    }

    private function providerName(string $channel): string
    {
        return match ($channel) {
            NotificationChannel::Database->value => 'database',
            NotificationChannel::Email->value => 'laravel-mail',
            NotificationChannel::Sms->value => config('notifications.sms_provider', 'log'),
            NotificationChannel::Push->value => config('notifications.push_provider', 'log'),
            default => 'unknown',
        };
    }
}
