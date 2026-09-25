<?php

namespace App\Providers;

use App\Models\BlogPost;
use App\Models\Car;
use App\Models\User;
use App\Policies\BlogPostPolicy;
use App\Policies\CarPolicy;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(BlogPost::class, BlogPostPolicy::class);
        Gate::policy(Car::class, CarPolicy::class);
        Gate::define('viewAnalytics', fn (User $user): bool => (bool) $user->is_analytics_admin);
    }
}
