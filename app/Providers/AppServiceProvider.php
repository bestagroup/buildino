<?php

namespace App\Providers;

use App\Models\Building;
use App\Models\Unit;
use App\Models\User;
use App\Observers\ProvisionSubscriptionObserver;
use App\Observers\ProvisionWalletObserver;
use App\Services\Web\ManagementHeaderContextService;
use App\Services\Web\ManagementUiContextService;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            ManagementUiContextService::class
        );

        $this->app->singleton(
            ManagementHeaderContextService::class
        );
    }

    public function boot(): void
    {
        /*
         * Wallet and subscription provisioning are part of the Building domain
         * lifecycle. Both observers are idempotent.
         */
        User::observe(ProvisionWalletObserver::class);
        Unit::observe(ProvisionWalletObserver::class);
        Building::observe(ProvisionWalletObserver::class);
        Building::observe(ProvisionSubscriptionObserver::class);

        ResetPasswordNotification::createUrlUsing(
            function (User $user, string $token): string {
                return route(
                    'password.reset',
                    [
                        'token' => $token,
                        'email' => $user->getEmailForPasswordReset(),
                    ]
                );
            }
        );

        View::composer(
            'management.*',
            function ($view): void {
                $user = Auth::guard('web')->user()
                    ?? request()->user();

                if (! $user) {
                    return;
                }

                $view->with('user', $user);

                $view->with(
                    'managementUi',
                    app(ManagementUiContextService::class)->context($user)
                );

                $view->with(
                    'managementHeader',
                    app(ManagementHeaderContextService::class)->context($user)
                );
            }
        );
    }
}
