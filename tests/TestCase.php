<?php

namespace MeShaon\LaravelResilience\Tests;

use MeShaon\LaravelResilience\Facades\Resilience;
use MeShaon\LaravelResilience\LaravelResilienceServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('cache.stores.array', [
            'driver' => 'array',
            'serialize' => false,
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelResilienceServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Resilience' => Resilience::class,
        ];
    }
}
