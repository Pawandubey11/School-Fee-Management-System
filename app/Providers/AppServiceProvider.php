<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

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
       if (config('app.env') === 'production' || request()->header('x-forwarded-proto') === 'https' || str_starts_with(env('APP_URL', ''), 'https://')) {
           \Illuminate\Support\Facades\URL::forceScheme('https');
       }
    }
}
