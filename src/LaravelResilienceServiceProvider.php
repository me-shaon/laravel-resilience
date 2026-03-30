<?php

namespace MeShaon\LaravelResilience;

use MeShaon\LaravelResilience\Commands\DiscoverResilienceRisksCommand;
use MeShaon\LaravelResilience\Commands\RunResilienceScenarioCommand;
use MeShaon\LaravelResilience\Commands\SuggestResilienceImprovementsCommand;
use MeShaon\LaravelResilience\Discovery\DiscoveryScanner;
use MeShaon\LaravelResilience\Faults\FaultManager;
use MeShaon\LaravelResilience\Faults\Injectors\ContainerFaultInjector;
use MeShaon\LaravelResilience\Reporting\HtmlReportGenerator;
use MeShaon\LaravelResilience\Scenarios\ScenarioRunner;
use MeShaon\LaravelResilience\Suggestions\SuggestionEngine;
use MeShaon\LaravelResilience\Support\EnvironmentGuard;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelResilienceServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-resilience')
            ->hasConfigFile()
            ->hasCommand(DiscoverResilienceRisksCommand::class)
            ->hasCommand(RunResilienceScenarioCommand::class)
            ->hasCommand(SuggestResilienceImprovementsCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(EnvironmentGuard::class);
        $this->app->singleton(DiscoveryScanner::class);
        $this->app->singleton(FaultManager::class);
        $this->app->singleton(ContainerFaultInjector::class);
        $this->app->singleton(HtmlReportGenerator::class);
        $this->app->singleton(ScenarioRunner::class);
        $this->app->singleton(SuggestionEngine::class);

        $this->app->singleton(
            'resilience',
            fn (): LaravelResilience => new LaravelResilience(
                $this->app->make(EnvironmentGuard::class),
                $this->app->make(FaultManager::class),
                $this->app->make(ContainerFaultInjector::class)
            )
        );

        $this->app->alias('resilience', LaravelResilience::class);
    }
}
