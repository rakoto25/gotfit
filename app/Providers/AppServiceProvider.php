<?php

namespace App\Providers;

use App\Models\User;
use App\Notifications\CoachApprovedNotification;
use Illuminate\Support\Facades\Schema;
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
        Schema::defaultStringLength(191);

        User::updated(function (User $user) {
            if ($user->wasChanged('account_status') && $user->account_status === 'approved'
                && $user->hasRole('intervenant')) {
                $user->notify(new CoachApprovedNotification);
            }
        });
    }
}
