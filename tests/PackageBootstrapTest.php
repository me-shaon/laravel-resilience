<?php

use MeShaon\LaravelResilience\Facades\Resilience;
use MeShaon\LaravelResilience\LaravelResilience;

it('loads the package config using the resilience key', function () {
    expect(config('resilience'))->toBeArray();
});

it('resolves the package root from the container', function () {
    expect(app(LaravelResilience::class))->toBeInstanceOf(LaravelResilience::class);
});

it('resolves the facade root', function () {
    expect(Resilience::getFacadeRoot())->toBeInstanceOf(LaravelResilience::class);
});
