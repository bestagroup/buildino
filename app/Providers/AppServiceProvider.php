<?php

namespace App\Providers;

use App\Observers\ProvisionWalletObserver;
use App\Services\Web\ManagementUiContextService;
use App\Models\User;
use App\Models\Unit;
use App\Models\Building;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            ManagementUiContextService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Every monetary owner receives its Wallet as part of the
         * domain lifecycle. WalletService::walletFor() remains
         * idempotent, so repeated provisioning is safe.
         */
        User::observe(ProvisionWalletObserver::class);
        Unit::observe(ProvisionWalletObserver::class);
        Building::observe(ProvisionWalletObserver::class);

        ResetPasswordNotification::createUrlUsing(
            function (
                User $user,
                string $token
            ): string {
                return route(
                    'password.reset',
                    [
                        'token' => $token,
                        'email' =>
                            $user
                                ->getEmailForPasswordReset(),
                    ]
                );
            }
        );

        View::composer(
            'management.*',
            function ($view): void {
                $user =
                    Auth::guard('web')->user()
                    ?? request()->user();

                if (! $user) {
                    return;
                }

                $view->with(
                    'managementUi',
                    app(
                        ManagementUiContextService::class
                    )->context($user)
                );
            }
        );
    }
}
