<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Validation\ValidationException;

final class NotificationDeviceService
{
    public function sync(
        User $user,
        array $data
    ): UserDevice {
        $deviceId =
            trim(
                (string) (
                    $data['device_id']
                    ?? ''
                )
            );

        if ($deviceId === '') {
            throw ValidationException::withMessages([
                'device_id' =>
                    'Device identifier is required.',
            ]);
        }

        $existing =
            UserDevice::query()
                ->where(
                    'device_id',
                    $deviceId
                )
                ->first();

        if (
            $existing
            && (int) $existing->user_id
                !== (int) $user->getKey()
        ) {
            /*
             * device_id is a physical/app installation identity. Reassigning
             * it silently would allow cross-account push leakage.
             */
            throw ValidationException::withMessages([
                'device_id' =>
                    'This device is already registered to another user.',
            ]);
        }

        $pushTokenSupplied =
            array_key_exists(
                'push_token',
                $data
            );

        $pushToken =
            $pushTokenSupplied
                ? trim(
                    (string) (
                        $data[
                            'push_token'
                        ]
                        ?? ''
                    )
                )
                : null;

        if (
            $pushTokenSupplied
            && $pushToken === ''
        ) {
            $pushToken =
                null;
        }

        if ($pushToken !== null) {
            /*
             * A Firebase/APNs endpoint token must belong to one current
             * account only. Revoke the same token from every other account.
             */
            UserDevice::query()
                ->where(
                    'push_token',
                    $pushToken
                )
                ->where(
                    'user_id',
                    '!=',
                    $user->getKey()
                )
                ->update([
                    'push_token' =>
                        null,
                ]);
        }

        $updates = [
            'platform' =>
                $data['platform']
                ?? (
                    $existing
                        ?->platform
                ),

            'device_name' =>
                $data['device_name']
                ?? (
                    $existing
                        ?->device_name
                ),

            'last_used_at' =>
                now(),
        ];

        if ($pushTokenSupplied) {
            $updates[
                'push_token'
            ] =
                $pushToken;
        }

        return UserDevice::query()
            ->updateOrCreate(
                [
                    'user_id' =>
                        $user->getKey(),

                    'device_id' =>
                        $deviceId,
                ],
                $updates
            );
    }

    public function release(
        User $user,
        ?string $deviceId
    ): bool {
        $deviceId =
            trim(
                (string) $deviceId
            );

        if ($deviceId === '') {
            return false;
        }

        return UserDevice::query()
            ->where(
                'user_id',
                $user->getKey()
            )
            ->where(
                'device_id',
                $deviceId
            )
            ->delete() > 0;
    }
}
