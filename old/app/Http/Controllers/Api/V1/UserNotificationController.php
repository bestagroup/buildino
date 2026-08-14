<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\NotificationChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterNotificationDeviceRequest;
use App\Http\Requests\UpdateNotificationPreferencesRequest;
use App\Models\NotificationLog;
use App\Models\UserDevice;
use App\Models\UserNotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $items = NotificationLog::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->when(
                $request->filled('channel'),
                fn ($query) => $query->where(
                    'channel',
                    $request->string('channel')->toString()
                )
            )
            ->latest('id')
            ->paginate(
                min(max((int) $request->integer('per_page', 20), 1), 100)
            );

        return response()->json($items);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        $count = NotificationLog::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->where('channel', NotificationChannel::Database->value)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'data' => ['unread_count' => $count],
        ]);
    }

    public function markRead(
        Request $request,
        NotificationLog $notificationLog
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $notificationLog->notifiable_type === $user->getMorphClass()
            && (int) $notificationLog->notifiable_id === (int) $user->getKey(),
            403
        );

        $notificationLog->forceFill([
            'read_at' => $notificationLog->read_at ?? now(),
        ])->save();

        return response()->json([
            'data' => $notificationLog,
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();

        $updated = NotificationLog::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->where('channel', NotificationChannel::Database->value)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'data' => ['updated' => $updated],
        ]);
    }

    public function devices(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()
                ->userDevices()
                ->latest('last_used_at')
                ->get(),
        ]);
    }

    public function registerDevice(
        RegisterNotificationDeviceRequest $request
    ): JsonResponse {
        $user = $request->user();
        $data = $request->validated();

        $existing = UserDevice::query()
            ->where('device_id', $data['device_id'])
            ->first();

        if ($existing && (int) $existing->user_id !== (int) $user->getKey()) {
            throw ValidationException::withMessages([
                'device_id' => 'This device is already registered to another user.',
            ]);
        }

        if (! empty($data['push_token'])) {
            /*
             * Push tokens are endpoint identities. When a phone changes
             * account, revoke the same token from every other user first to
             * prevent cross-account notification leakage.
             */
            UserDevice::query()
                ->where('push_token', $data['push_token'])
                ->where('user_id', '!=', $user->getKey())
                ->update(['push_token' => null]);
        }

        $device = UserDevice::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'device_id' => $data['device_id'],
            ],
            [
                'platform' => $data['platform'],
                'device_name' => $data['device_name'] ?? null,
                'push_token' => $data['push_token'] ?? null,
                'last_used_at' => now(),
            ]
        );

        /*
         * updateOrCreate() already returns the persisted model with its
         * primary key and timestamps. Do not call refresh() here: Eloquent
         * refresh() performs a firstOrFail() internally, which is unnecessary
         * for this response and can be converted by Laravel into a
         * NotFoundHttpException.
         */
        return response()->json([
            'data' => $device,
        ], $device->wasRecentlyCreated ? 201 : 200);
    }

    public function deleteDevice(
        Request $request,
        UserDevice $userDevice
    ): JsonResponse {
        abort_unless(
            (int) $userDevice->user_id === (int) $request->user()->getKey(),
            403
        );

        $userDevice->delete();

        return response()->json(status: 204);
    }

    public function preferences(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()
                ->userNotificationPreferences()
                ->orderBy('notification_type')
                ->orderBy('channel')
                ->get(),
        ]);
    }

    public function updatePreferences(
        UpdateNotificationPreferencesRequest $request
    ): JsonResponse {
        $user = $request->user();

        foreach ($request->validated('preferences') as $preference) {
            UserNotificationPreference::query()->updateOrCreate(
                [
                    'user_id' => $user->getKey(),
                    'notification_type' => $preference['notification_type'],
                    'channel' => $preference['channel'],
                ],
                [
                    'is_enabled' => $preference['is_enabled'],
                ]
            );
        }

        return $this->preferences($request);
    }
}
