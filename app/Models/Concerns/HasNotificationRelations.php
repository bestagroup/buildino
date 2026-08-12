<?php

namespace App\Models\Concerns;

use App\Models\UserDevice;
use App\Models\UserNotificationPreference;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasNotificationRelations
{
    public function userNotificationPreferences(): HasMany
    {
        return $this->hasMany(UserNotificationPreference::class, 'user_id');
    }

    public function notificationDevices(): HasMany
    {
        return $this->hasMany(UserDevice::class, 'user_id');
    }
}
