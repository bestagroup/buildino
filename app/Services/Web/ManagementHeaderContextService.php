<?php

namespace App\Services\Web;

use App\Enums\NotificationChannel;
use App\Models\Building;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\Security\BuildingPermissionScopeService;
use App\Support\Authorization\PermissionChecker;
use App\Support\Jalali\JalaliDateFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ManagementHeaderContextService
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $cache = [];

    public function __construct(
        private readonly JalaliDateFormatter $jalali
    ) {}

    public function managementContext(User $user, Request $request): array
    {
        $context = $this->context($user);
        $context['wallet']['label'] = 'کیف پول شخصی شما';
        $ids = app(BuildingPermissionScopeService::class)->buildingIds($user, 'building-wallet.view');
        $buildings = Building::query()
            ->when($ids !== null, fn ($query) => $query->whereIn('id', $ids))
            ->orderBy('title')->get()
            ->filter(fn (Building $building) => app(PermissionChecker::class)->allows($user, 'building-wallet.view', $building));

        $routeBuilding = $request->route('building');
        $requestedId = $request->query('building_id', $routeBuilding instanceof Building ? $routeBuilding->id : $routeBuilding);
        $building = $requestedId !== null
            ? $buildings->firstWhere('id', $requestedId)
            : ($buildings->count() === 1 ? $buildings->first() : null);

        $context['building_wallet_links'] = $buildings->map(fn (Building $item) => [
            'title' => $item->title,
            'url' => route('management.dashboard', ['building_id' => $item->id]),
        ])->values()->all();

        if ($building) {
            // Read the same owner and currency as cash documents; never create wallets during rendering.
            $currency = $building->currency ?: 'IRR';
            $wallet = $building->wallets()->where('currency', $currency)->first();
            $context['wallet'] = [
                'exists' => $wallet !== null,
                'label' => 'کیف پول ساختمان '.$building->title,
                'balance' => (int) ($wallet?->balance ?? 0),
                'locked_balance' => (int) ($wallet?->locked_balance ?? 0),
                'available_balance' => $wallet && $wallet->is_active ? $wallet->availableBalance() : 0,
                'currency' => $currency,
                'uuid' => $wallet?->uuid,
            ];
        }

        return $context;
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
                            'id' => $notification->getKey(),

                            'title' => $notification->title,

                            'message' => $notification->message,

                            'type' => $notification->notification_type,

                            'is_read' => $notification->read_at !== null,

                            'read_at' => $notification
                                ->read_at
                                ?->toISOString(),

                            'created_at' => $notification
                                ->created_at
                                ?->toISOString(),

                            'created_at_jalali' => $this->jalali
                                ->dateTime(
                                    $notification
                                        ->created_at
                                ),

                            'href' => $href,
                        ];
                    }
                )
                ->values()
                ->all();

        return $this->cache[
            $userId
        ] = [
            'wallet' => [
                'exists' => $wallet !== null,

                'balance' => (int) (
                    $wallet
                        ?->balance
                    ?? 0
                ),

                'locked_balance' => (int) (
                    $wallet
                        ?->locked_balance
                    ?? 0
                ),

                'available_balance' => $wallet
                    ?->availableBalance()
                    ?? 0,

                'currency' => $wallet
                    ?->currency
                    ?? 'IRR',

                'uuid' => $wallet
                    ?->uuid,
            ],

            'notifications' => [
                'unread_count' => $unreadCount,

                'items' => $notifications,
            ],

            'jalali_source' => $this->jalali
                ->source(),
        ];
    }
}
