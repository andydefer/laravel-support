<?php

declare(strict_types=1);

namespace AndyDefer\Support;

use AndyDefer\Support\Contracts\Services\SupportServiceInterface;
use AndyDefer\Support\Contracts\Services\TicketRouteManagerServiceInterface;
use AndyDefer\Support\Repositories\TicketMessageRepository;
use AndyDefer\Support\Repositories\TicketRepository;
use AndyDefer\Support\Services\SupportService;
use AndyDefer\Support\Services\TicketRouteManagerService;
use Illuminate\Support\ServiceProvider;

final class SupportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Repositories
        $this->app->singleton(TicketRepository::class);
        $this->app->singleton(TicketMessageRepository::class);

        // Services
        $this->app->singleton(SupportService::class);
        $this->app->singleton(TicketRouteManagerService::class);

        // Bindings
        $this->app->bind(
            SupportServiceInterface::class,
            SupportService::class,
        );

        $this->app->bind(
            TicketRouteManagerServiceInterface::class,
            TicketRouteManagerService::class,
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'support-migrations');

            $this->publishes([
                __DIR__.'/../routes/support.php' => base_path('routes/support.php'),
            ], 'support-routes');
        }
    }
}
