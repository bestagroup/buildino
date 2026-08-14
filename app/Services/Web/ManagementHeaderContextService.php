<?php

namespace App\Services\Web;

use App\Enums\NotificationChannel;
use App\Models\NotificationLog;
use App\Models\User;
use App\Support\Jalali\JalaliDateFormatter;
use Illuminate\Support\Str;

final class ManagementHeaderContextService
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $cache = [];

    public function __construct(
        private readonly JalaliDateFormatter $jalali
    ) {
    }

    public function context(
        User $user
    ): array {
        $userId =
            (int) $user->getKey();

        if (
            isset(
                $this->cache[
                    $userId
                ]
            )
        ) {
            return $this->cache[
                $userId
            ];
        }

        $wallet =
            $user
                ->wallets()
                ->where(
                    'currency',
                    'IRR'
                )
                ->where(
                    'is_active',
                    true
                )
                ->first();

        $notificationBase =
            NotificationLog::query()
                ->where(
                    'notifiable_type',
                    $user->getMorphClass()
                )
                ->where(
                    'notifiable_id',
                    $userId
                )
                ->where(
                    'channel',
                    NotificationChannel::Database->value
                );

        $unreadCount =
            (clone $notificationBase)
                ->whereNull(
                    'read_at'
                )
                ->count();

        $notifications =
            (clone $notificationBase)
                ->latest('id')
                ->limit(7)
                ->get()
                ->map(
                    function (
                        NotificationLog $notification
                    ): array {
                        $href =
                            data_get(
                                $notification->response,
                                'data.url'
                            );

                        if (
                            ! is_string($href)
                            || ! Str::startsWith(
                                $href,
                                '/'
                            )
                            || Str::startsWith(
                                $href,
                                '//'
                            )
                        ) {
                            $href = null;
                        }

                        return [
                            'id' =>
                                $notification->getKey(),

                            'title' =>
                                $notification->title,

                            'message' =>
                                $notification->message,

                            'type' =>
                                $notification->notification_type,

                            'is_read' =>
                                $notification->read_at !== null,

                            'read_at' =>
                                $notification
                                    ->read_at
                                    ?->toISOString(),

                            'created_at' =>
                                $notification
                                    ->created_at
                                    ?->toISOString(),

                            'created_at_jalali' =>
                                $this->jalali
                                    ->dateTime(
                                        $notification
                                            ->created_at
                                    ),

                            'href' =>
                                $href,
                        ];
                    }
                )
                ->values()
                ->all();

        return $this->cache[
            $userId
        ] = [
            'wallet' => [
                'exists' =>
                    $wallet !== null,

                'balance' =>
                    (int) (
                        $wallet
                            ?->balance
                        ?? 0
                    ),

                'locked_balance' =>
                    (int) (
                        $wallet
                            ?->locked_balance
                        ?? 0
                    ),

                'available_balance' =>
                    $wallet
                        ?->availableBalance()
                    ?? 0,

                'currency' =>
                    $wallet
                        ?->currency
                    ?? 'IRR',

                'uuid' =>
                    $wallet
                        ?->uuid,
            ],

            'notifications' => [
                'unread_count' =>
                    $unreadCount,

                'items' =>
                    $notifications,
            ],

            'jalali_source' =>
                $this->jalali
                    ->source(),
        ];
    }
}
