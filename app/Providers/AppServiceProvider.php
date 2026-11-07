<?php

namespace App\Providers;

use App\Support\BrandResolver;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(BrandResolver::class);
        $this->app->scoped(\App\Services\Settings\RestaurantSettings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Every page uses Bootstrap 5; the default Tailwind paginator renders unstyled.
        Paginator::useBootstrapFive();
        Paginator::defaultView('pagination.brand');

        // Views can pass their own $brand (e.g. emails for a specific restaurant).
        View::composer('*', function ($view): void {
            if (!array_key_exists('brand', $view->getData())) {
                $view->with('brand', $this->app->make(BrandResolver::class)->current());
            }
        });
    }
}
