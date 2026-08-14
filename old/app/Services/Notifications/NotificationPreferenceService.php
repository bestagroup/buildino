<?php

namespace App\Services\Notifications;

use App\Models\User;

class NotificationPreferenceService
{
    public function enabled(User $user, string $type, string $channel): bool
    {
        $preference = $user->userNotificationPreferences()
            ->where('notification_type', $type)
            ->where('channel', $channel)
            ->first();

        return $preference?->is_enabled ?? true;
    }
}
