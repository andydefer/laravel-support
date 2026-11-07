<?php

declare(strict_types=1);

namespace AndyDefer\LaravelRatings;

use AndyDefer\LaravelRatings\Contracts\Services\RatingServiceInterface;
use AndyDefer\LaravelRatings\Repositories\RatingRepository;
use AndyDefer\LaravelRatings\Services\RatingService;
use Illuminate\Support\ServiceProvider;

final class RatingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ✅ Repository - Singleton pour éviter les multi-connexions
        $this->app->singleton(RatingRepository::class);

        // ✅ Service - Singleton
        $this->app->singleton(RatingService::class);

        // ✅ Bind de l'interface vers l'implémentation concrète
        $this->app->bind(
            RatingServiceInterface::class,
            RatingService::class
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/migrations');
        }

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'ratings-migrations');
    }
}
