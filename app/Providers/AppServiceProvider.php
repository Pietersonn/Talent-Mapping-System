<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View; // Tambahan untuk View Composer
use Illuminate\Support\Facades\Auth; // Tambahan untuk Auth

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
        // View Composer: Inject variabel $user ke sidebar dan navbar
        // Ini solusi paling rapi untuk menghilangkan error "Undefined property"
        View::composer(
            ['admin.layouts.partials.sidebar', 'admin.layouts.partials.navbar' ,'admin.layouts.app.blade.php'],
            function ($view) {
                $view->with('user', Auth::user());
            }
        );

        // Konfigurasi URL & Paginator Existing
        if (config('app.env') === 'production') {
            URL::forceRootUrl(config('app.url'));
            URL::forceScheme('https');
        }

        Paginator::useBootstrapFour();
    }
}
