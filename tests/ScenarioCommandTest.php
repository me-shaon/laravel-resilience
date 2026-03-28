<?php

use Illuminate\Support\Facades\Artisan;
use MeShaon\LaravelResilience\Tests\Fixtures\Payments\FakePaymentGateway;
use MeShaon\LaravelResilience\Tests\Fixtures\Payments\PaymentGateway;
use MeShaon\LaravelResilience\Tests\Fixtures\Scenarios\FailingSearchScenario;
use MeShaon\LaravelResilience\Tests\Fixtures\Scenarios\SuccessfulFallbackScenario;
use MeShaon\LaravelResilience\Tests\Fixtures\Search\FakeSearchClient;
use MeShaon\LaravelResilience\Tests\Fixtures\Search\SearchClient;

it('runs a configured scenario from artisan and prints a readable success report', function () {
    app()->singleton(PaymentGateway::class, FakePaymentGateway::class);

    config()->set('resilience.scenarios', [
        'payment-fallback' => SuccessfulFallbackScenario::class,
    ]);

    $exitCode = Artisan::call('resilience:run', ['scenario' => 'payment-fallback']);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Scenario [payment-fallback]')
        ->and($output)->toContain('completed successfully');
});

it('prints a readable failure message when a scenario fails', function () {
    app()->bind(SearchClient::class, FakeSearchClient::class);

    config()->set('resilience.scenarios', [
        'search-outage' => FailingSearchScenario::class,
    ]);

    $exitCode = Artisan::call('resilience:run', ['scenario' => 'search-outage']);
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('Scenario [search-outage]')
        ->and($output)->toContain('Search is down.');
});

it('supports json output for scenario reports', function () {
    app()->singleton(PaymentGateway::class, FakePaymentGateway::class);

    config()->set('resilience.scenarios', [
        'payment-fallback' => SuccessfulFallbackScenario::class,
    ]);

    $exitCode = Artisan::call('resilience:run', ['scenario' => 'payment-fallback', '--json' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('"name"')
        ->and($output)->toContain('payment-fallback')
        ->and($output)->toContain('success');
});

it('shows a readable error when a blocked environment prevents running a scenario', function () {
    $this->app['env'] = 'production';
    config()->set('resilience.blocked_environments', ['production']);
    config()->set('resilience.scenarios', [
        'payment-fallback' => SuccessfulFallbackScenario::class,
    ]);

    app()->singleton(PaymentGateway::class, FakePaymentGateway::class);

    $exitCode = Artisan::call('resilience:run', ['scenario' => 'payment-fallback']);
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('Unable to run scenario [payment-fallback]');
});
