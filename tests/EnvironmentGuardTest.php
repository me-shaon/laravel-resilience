<?php

use MeShaon\LaravelResilience\Exceptions\ActivationNotAllowed;
use MeShaon\LaravelResilience\Facades\Resilience;

it('allows activation by default in non-production environments', function () {
    $this->app['env'] = 'testing';

    expect(Resilience::enabled())->toBeTrue()
        ->and(Resilience::canActivate())->toBeTrue();

    Resilience::ensureCanActivate();
});

it('blocks activation in production by default', function () {
    $this->app['env'] = 'production';

    expect(Resilience::canActivate())->toBeFalse()
        ->and(fn () => Resilience::ensureCanActivate('Fault injection'))
        ->toThrow(
            ActivationNotAllowed::class,
            'Fault injection cannot be activated in the [production] environment. Blocked environments: [production].'
        );
});

it('allows production activation when production is removed from the blocked list', function () {
    $this->app['env'] = 'production';

    config()->set('resilience.blocked_environments', []);

    expect(Resilience::canActivate())->toBeTrue();
});

it('honors the global kill switch', function () {
    $this->app['env'] = 'testing';

    config()->set('resilience.enabled', false);

    expect(Resilience::enabled())->toBeFalse()
        ->and(Resilience::canActivate())->toBeFalse()
        ->and(fn () => Resilience::ensureCanActivate())
        ->toThrow(
            ActivationNotAllowed::class,
            'Laravel Resilience is disabled by the global kill switch [resilience.enabled].'
        );
});

it('can block activation for an explicit environment list', function () {
    $this->app['env'] = 'staging';

    config()->set('resilience.blocked_environments', ['production', 'staging']);

    expect(fn () => Resilience::ensureCanActivate('Resilience scenarios'))
        ->toThrow(
            ActivationNotAllowed::class,
            'Resilience scenarios cannot be activated in the [staging] environment. Blocked environments: [production, staging].'
        );
});
