<?php

declare(strict_types=1);

namespace AndyDefer\Support\Tests;

use AndyDefer\LaravelNotification\NotificationServiceProvider;
use AndyDefer\Logger\LoggerServiceProvider;
use AndyDefer\Support\SupportServiceProvider;
use AndyDefer\Task\TaskServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class IntegrationTestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            LoggerServiceProvider::class,
            TaskServiceProvider::class,
            NotificationServiceProvider::class,
            SupportServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function runMigrations(): void
    {
        $migrationPaths = [
            __DIR__.'/../database/migrations',
            __DIR__.'/Fixtures/database/migrations',
        ];

        foreach ($migrationPaths as $path) {
            if (is_dir($path)) {
                $this->loadMigrationsFrom($path);
            }
        }
    }
}
