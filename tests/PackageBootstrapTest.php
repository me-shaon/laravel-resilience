<?php

use MeShaon\LaravelResilience\Facades\Resilience;
use MeShaon\LaravelResilience\LaravelResilience;
use MeShaon\LaravelResilience\LaravelResilienceServiceProvider;

it('registers the package service provider', function () {
    expect(app()->getLoadedProviders())
        ->toHaveKey(LaravelResilienceServiceProvider::class, true);
});

it('loads the package config using the resilience key', function () {
    expect(config('resilience'))->toMatchArray([
        'enabled' => true,
        'blocked_environments' => ['production'],
    ]);
});

it('resolves the package root from the container', function () {
    expect(app(LaravelResilience::class))->toBeInstanceOf(LaravelResilience::class);
});

it('resolves the facade root', function () {
    expect(Resilience::getFacadeRoot())->toBeInstanceOf(LaravelResilience::class);
});

it('reports activation status through the facade', function () {
    expect(Resilience::activationStatus())
        ->toMatchArray([
            'enabled' => true,
            'blocked_environments' => ['production'],
        ])
        ->and(Resilience::activationStatus())
        ->toHaveKeys([
            'can_activate',
            'current_environment',
            'blocked_environments',
        ]);
});
