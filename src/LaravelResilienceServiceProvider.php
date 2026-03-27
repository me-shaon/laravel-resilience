<?php

namespace MeShaon\LaravelResilience;

use MeShaon\LaravelResilience\Support\EnvironmentGuard;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelResilienceServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-resilience')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(EnvironmentGuard::class);

        $this->app->singleton(
            'resilience',
            fn (): LaravelResilience => new LaravelResilience($this->app->make(EnvironmentGuard::class))
        );

        $this->app->alias('resilience', LaravelResilience::class);
    }
}
