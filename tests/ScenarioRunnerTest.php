<?php

use Illuminate\Support\Facades\Log;
use MeShaon\LaravelResilience\Exceptions\ActivationNotAllowed;
use MeShaon\LaravelResilience\Scenarios\ScenarioRunner;
use MeShaon\LaravelResilience\Tests\Fixtures\Payments\FakePaymentGateway;
use MeShaon\LaravelResilience\Tests\Fixtures\Payments\PaymentGateway;
use MeShaon\LaravelResilience\Tests\Fixtures\Scenarios\FailingSearchScenario;
use MeShaon\LaravelResilience\Tests\Fixtures\Scenarios\SuccessfulFallbackScenario;
use MeShaon\LaravelResilience\Tests\Fixtures\Search\FakeSearchClient;
use MeShaon\LaravelResilience\Tests\Fixtures\Search\SearchClient;

it('runs configured scenarios and returns an auditable success report', function () {
    Log::spy();

    app()->singleton(PaymentGateway::class, FakePaymentGateway::class);

    config()->set('resilience.scenarios', [
        'payment-fallback' => SuccessfulFallbackScenario::class,
    ]);

    $report = app(ScenarioRunner::class)->run('payment-fallback');

    expect($report->successful())->toBeTrue()
        ->and($report->name())->toBe('payment-fallback')
        ->and($report->result())->toBe([
            'fallback_used' => true,
            'message' => 'Operation timed out.',
        ])
        ->and($report->activatedFaults())->toBe(['payment-timeout']);

    Log::shouldHaveReceived('info')->withArgs(
        fn (string $message, array $context): bool => $message === 'resilience.scenario_ran'
            && $context['name'] === 'payment-fallback'
            && $context['status'] === 'success'
    );
});

it('returns a failed scenario report and logs the failure without leaving faults active', function () {
    Log::spy();

    app()->bind(SearchClient::class, FakeSearchClient::class);

    config()->set('resilience.scenarios', [
        'search-outage' => FailingSearchScenario::class,
    ]);

    $report = app(ScenarioRunner::class)->run('search-outage');

    expect($report->successful())->toBeFalse()
        ->and($report->exceptionMessage())->toBe('Search is down.')
        ->and(Resilience::activeFaults())->toBe([]);

    Log::shouldHaveReceived('error')->withArgs(
        fn (string $message, array $context): bool => $message === 'resilience.scenario_ran'
            && $context['name'] === 'search-outage'
            && $context['status'] === 'failed'
            && ($context['exception']['message'] ?? null) === 'Search is down.'
    );
});

it('enforces environment restrictions when running scenarios', function () {
    $this->app['env'] = 'production';
    config()->set('resilience.blocked_environments', ['production']);
    config()->set('resilience.scenarios', [
        'payment-fallback' => SuccessfulFallbackScenario::class,
    ]);

    app()->singleton(PaymentGateway::class, FakePaymentGateway::class);

    expect(fn () => app(ScenarioRunner::class)->run('payment-fallback'))
        ->toThrow(ActivationNotAllowed::class, 'Scenario [payment-fallback]');
});
