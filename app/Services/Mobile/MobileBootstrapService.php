<?php

namespace App\Services\Mobile;

use App\Enums\NotificationChannel;
use App\Http\Resources\V1\AuthUserResource;
use App\Models\NotificationLog;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Web\ManagementDashboardAccessService;
use App\Services\Web\PortalAccessService;
use App\Services\Wallet\WalletService;
use Illuminate\Http\Request;

final class MobileBootstrapService
{
    public function __construct(
        private readonly PortalAccessService $portalAccess,
        private readonly ManagementDashboardAccessService $managementAccess,
        private readonly WalletService $wallets
    ) {
    }

    public function build(
        User $user,
        Request $request
    ): array {
        $residentUnits =
            $this->portalAccess
                ->residentUnits(
                    $user
                );

        $resident =
            $residentUnits
                ->isNotEmpty();

        $provider =
            $this->portalAccess
                ->hasProviderAccess(
                    $user
                );

        $management =
            $this->managementAccess
                ->hasAnyAccess(
                    $user
                );

        $personalWallet =
            $this->wallets
                ->walletFor(
                    $user,
                    'IRR'
                );

        $deviceId =
            trim(
                (string) (
                    $request->header(
                        'X-Device-Id'
                    )
                    ?: $request->query(
                        'device_id',
                        ''
                    )
                )
            );

        $device =
            $deviceId !== ''
                ? $user
                    ->userDevices()
                    ->where(
                        'device_id',
                        $deviceId
                    )
                    ->first()
                : null;

        $appVersion =
            trim(
                (string) $request->header(
                    'X-App-Version',
                    ''
                )
            );

        $minimumVersion =
            (string) config(
                'mobile.minimum_supported_version',
                '1.0.0'
            );

        $latestVersion =
            (string) config(
                'mobile.latest_version',
                $minimumVersion
            );

        return [
            'api' => [
                'version' =>
                    (string) config(
                        'mobile.api_version',
                        'v1'
                    ),

                'server_time' =>
                    now()->toISOString(),
            ],

            'app' => [
                'client_version' =>
                    $appVersion
                    !== ''
                        ? $appVersion
                        : null,

                'minimum_supported_version' =>
                    $minimumVersion,

                'latest_version' =>
                    $latestVersion,

                'upgrade_required' =>
                    $appVersion !== ''
                    && version_compare(
                        $appVersion,
                        $minimumVersion,
                        '<'
                    ),

                'update_available' =>
                    $appVersion !== ''
                    && version_compare(
                        $appVersion,
                        $latestVersion,
                        '<'
                    ),

                'maintenance_mode' =>
                    (bool) config(
                        'mobile.maintenance_mode',
                        false
                    ),

                'maintenance_message' =>
                    config(
                        'mobile.maintenance_message'
                    ),
            ],

            'user' =>
                (new AuthUserResource(
                    $user
                ))->resolve(
                    $request
                ),

            'personas' =>
                array_values(
                    array_filter([
                        $resident
                            ? 'resident'
                            : null,

                        $provider
                            ? 'provider'
                            : null,

                        $management
                            ? 'management'
                            : null,
                    ])
                ),

            'roles' =>
                $this->roles(
                    $user
                ),

            'resident' => [
                'enabled' =>
                    $resident,

                'units' =>
                    $residentUnits
                        ->map(
                            fn ($unit): array =>
                                $this->unit(
                                    $user,
                                    $unit
                                )
                        )
                        ->values()
                        ->all(),
            ],

            'provider' => [
                'enabled' =>
                    $provider,

                'active_jobs' =>
                    $provider
                        ? ServiceRequest::query()
                            ->where(
                                'assigned_to',
                                $user->getKey()
                            )
                            ->whereNotIn(
                                'status',
                                [
                                    'completed',
                                    'cancelled',
                                ]
                            )
                            ->count()
                        : 0,
            ],

            'wallet' => [
                'id' =>
                    $personalWallet
                        ->getKey(),

                'uuid' =>
                    $personalWallet
                        ->uuid,

                'balance' =>
                    (int) $personalWallet
                        ->balance,

                'locked_balance' =>
                    (int) $personalWallet
                        ->locked_balance,

                'available_balance' =>
                    $personalWallet
                        ->availableBalance(),

                'currency' =>
                    strtoupper(
                        $personalWallet
                            ->currency
                        ?: 'IRR'
                    ),
            ],

            'notifications' => [
                'unread_count' =>
                    NotificationLog::query()
                        ->where(
                            'notifiable_type',
                            $user
                                ->getMorphClass()
                        )
                        ->where(
                            'notifiable_id',
                            $user
                                ->getKey()
                        )
                        ->where(
                            'channel',
                            NotificationChannel::Database
                                ->value
                        )
                        ->whereNull(
                            'read_at'
                        )
                        ->count(),

                'push_provider' =>
                    (string) config(
                        'notifications.push_provider',
                        'log'
                    ),
            ],

            'device' => [
                'requested_device_id' =>
                    $deviceId
                    !== ''
                        ? $deviceId
                        : null,

                'registered' =>
                    $device !== null,

                'id' =>
                    $device
                        ?->getKey(),

                'platform' =>
                    $device
                        ?->platform,

                'device_name' =>
                    $device
                        ?->device_name,

                'push_enabled' =>
                    filled(
                        $device
                            ?->push_token
                    ),

                'last_used_at' =>
                    $device
                        ?->last_used_at
                        ?->toISOString(),
            ],

            'features' =>
                config(
                    'mobile.features',
                    []
                ),
        ];
    }

    private function roles(
        User $user
    ): array {
        return $user
            ->userRoleAssignments()
            ->active()
            ->with(
                'role:id,name,display_name'
            )
            ->get()
            ->map(
                fn ($assignment): array => [
                    'name' =>
                        $assignment
                            ->role
                            ?->name,

                    'display_name' =>
                        $assignment
                            ->role
                            ?->display_name,

                    'scope_type' =>
                        $assignment
                            ->scope_type,

                    'scope_id' =>
                        $assignment
                            ->scope_id,
                ]
            )
            ->values()
            ->all();
    }

    private function unit(
        User $user,
        $unit
    ): array {
        $building =
            $unit
                ->floor
                ?->block
                ?->building;

        $currency =
            strtoupper(
                $building
                    ?->currency
                ?: 'IRR'
            );

        $wallet =
            $this->wallets
                ->walletFor(
                    $unit,
                    $currency
                );

        return [
            'id' =>
                $unit->getKey(),

            'unit_number' =>
                $unit->unit_number,

            'title' =>
                $unit->title
                ?: (
                    'واحد '
                    . $unit->unit_number
                ),

            'building' => [
                'id' =>
                    $building
                        ?->getKey(),

                'title' =>
                    $building
                        ?->title,
            ],

            'complex' => [
                'id' =>
                    $building
                        ?->complex
                        ?->getKey(),

                'title' =>
                    $building
                        ?->complex
                        ?->title,
            ],

            'relationship' =>
                $this->portalAccess
                    ->residentRelationship(
                        $user,
                        $unit
                    ),

            'wallet' => [
                'id' =>
                    $wallet
                        ->getKey(),

                'available_balance' =>
                    $wallet
                        ->availableBalance(),

                'locked_balance' =>
                    (int) $wallet
                        ->locked_balance,

                'currency' =>
                    $currency,
            ],
        ];
    }
}
