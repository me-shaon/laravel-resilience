<?php

use MeShaon\LaravelResilience\Faults\FaultScope;
use MeShaon\LaravelResilience\Faults\FaultTarget;
use MeShaon\LaravelResilience\Tests\Fixtures\Payments\FakePaymentGateway;
use MeShaon\LaravelResilience\Tests\Fixtures\Payments\PaymentGateway;
use MeShaon\LaravelResilience\Tests\Fixtures\Search\FakeSearchClient;
use MeShaon\LaravelResilience\Tests\Fixtures\Search\SearchClient;

it('injects timeout faults into container-bound interfaces', function () {
    app()->singleton(PaymentGateway::class, FakePaymentGateway::class);

    Resilience::for(PaymentGateway::class)->timeout();

    expect(fn () => app(PaymentGateway::class)->charge(500))
        ->toThrow(RuntimeException::class, 'Operation timed out.');
});

it('restores the original container binding after deactivation', function () {
    app()->singleton(PaymentGateway::class, FakePaymentGateway::class);

    $original = app(PaymentGateway::class);

    Resilience::for(PaymentGateway::class)->timeout();

    $instrumented = app(PaymentGateway::class);

    expect(get_class($original))->toBe(FakePaymentGateway::class)
        ->and(get_class($instrumented))->not->toBe(FakePaymentGateway::class);

    Resilience::deactivate(FaultTarget::container(PaymentGateway::class));

    $restored = app(PaymentGateway::class);

    expect(get_class($restored))->toBe(FakePaymentGateway::class)
        ->and($restored->charge(500))->toBe('charged:500');
});

it('supports bound concrete services and multiple active container targets', function () {
    app()->bind(SearchClient::class, FakeSearchClient::class);
    app()->singleton(PaymentGateway::class, FakePaymentGateway::class);

    Resilience::for(SearchClient::class)->exception(new RuntimeException('Search is down.'));
    Resilience::for(PaymentGateway::class)->timeout();

    expect(fn () => app(SearchClient::class)->search('laravel'))
        ->toThrow(RuntimeException::class, 'Search is down.')
        ->and(fn () => app(PaymentGateway::class)->charge(800))
        ->toThrow(RuntimeException::class, 'Operation timed out.');
});

it('cleans up test-scoped container faults without touching process-scoped rules', function () {
    app()->singleton(PaymentGateway::class, FakePaymentGateway::class);
    app()->bind(SearchClient::class, FakeSearchClient::class);

    Resilience::for(PaymentGateway::class)->timeout();
    Resilience::for(SearchClient::class)->process()->exception(new RuntimeException('Search is down.'));

    Resilience::deactivateScope(FaultScope::Test);

    expect(app(PaymentGateway::class)->charge(250))->toBe('charged:250')
        ->and(fn () => app(SearchClient::class)->search('resilience'))
        ->toThrow(RuntimeException::class, 'Search is down.');
});
