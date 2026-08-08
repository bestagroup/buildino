<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    App\Providers\DomainEventServiceProvider::class,
    App\Providers\NotificationServiceProvider::class,
    App\Providers\ApiSecurityServiceProvider::class,
];
