<?php

namespace App\Providers;

use App\Observers\ProvisionWalletObserver;
use App\Models\User;
use App\Models\Unit;
use App\Models\Building;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
    }
}
