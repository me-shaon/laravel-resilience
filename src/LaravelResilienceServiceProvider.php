<?php

namespace MeShaon\LaravelResilience;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelResilienceServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('resilience')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton('resilience', static fn (): LaravelResilience => new LaravelResilience);

        $this->app->alias('resilience', LaravelResilience::class);
    }
}
