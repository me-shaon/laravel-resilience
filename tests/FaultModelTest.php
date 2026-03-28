<?php

use MeShaon\LaravelResilience\Exceptions\InvalidFaultConfiguration;
use MeShaon\LaravelResilience\Faults\FaultRule;
use MeShaon\LaravelResilience\Faults\FaultScope;
use MeShaon\LaravelResilience\Faults\FaultTarget;
use MeShaon\LaravelResilience\Faults\FaultType;

it('builds fault rules with the expected metadata', function () {
    $exception = new RuntimeException('Gateway failed.');
    $target = FaultTarget::container('payment-gateway');

    $rule = FaultRule::exception('payment-exception', $target, $exception, FaultScope::Process);
    $latency = FaultRule::latency('payment-latency', $target, 250);

    expect($rule->type())->toBe(FaultType::Exception)
        ->and($rule->exceptionToThrow())->toBe($exception)
        ->and($rule->activatesOn(1))->toBeTrue()
        ->and($latency->latencyInMilliseconds())->toBe(250)
        ->and($latency->type())->toBe(FaultType::Latency)
        ->and($rule->scope())->toBe(FaultScope::Process)
        ->and($rule->target()->key())->toBe('container:payment-gateway');
});

it('uses deterministic seeded rules for percentage-based faults', function () {
    $target = FaultTarget::integration('mail');
    $rule = FaultRule::percentage('mail-flaky', $target, 37.5, 'phase-two');

    $firstPass = [];
    $secondPass = [];

    foreach ([1, 2, 3, 4, 5, 6] as $attempt) {
        $firstPass[] = $rule->activatesOn($attempt);
        $secondPass[] = $rule->activatesOn($attempt);
    }

    expect($firstPass)
        ->toBe([true, true, false, false, false, false])
        ->and($secondPass)
        ->toBe($firstPass);
});

it('supports explicit attempt maps for deterministic percentage-style faults', function () {
    $target = FaultTarget::integration('cache');
    $rule = FaultRule::percentageOnAttempts('cache-flaky', $target, [2, 4, 7]);

    expect($rule->activatesOn(1))->toBeFalse()
        ->and($rule->activatesOn(2))->toBeTrue()
        ->and($rule->activatesOn(4))->toBeTrue()
        ->and($rule->activatesOn(5))->toBeFalse();
});

it('fails clearly for invalid fault rule configurations', function () {
    expect(fn () => FaultTarget::for('', 'payments'))
        ->toThrow(InvalidFaultConfiguration::class, 'Fault targets require a non-empty type.')
        ->and(fn () => FaultRule::latency('slow-payments', FaultTarget::container('payments'), 0))
        ->toThrow(InvalidFaultConfiguration::class, 'Latency faults require a positive number of milliseconds.')
        ->and(fn () => FaultRule::failFirst('fail-fast', FaultTarget::container('payments'), 0))
        ->toThrow(InvalidFaultConfiguration::class, 'Fail-first faults require at least one attempt.')
        ->and(fn () => FaultRule::percentage('payment-percentage', FaultTarget::container('payments'), 120, 'seed'))
        ->toThrow(InvalidFaultConfiguration::class, 'Percentage-based faults must be configured between 0 and 100.')
        ->and(fn () => FaultRule::percentage('payment-percentage', FaultTarget::container('payments'), 50, ' '))
        ->toThrow(InvalidFaultConfiguration::class, 'Percentage faults require a non-empty seed.')
        ->and(fn () => FaultRule::percentageOnAttempts('payment-map', FaultTarget::container('payments'), [0]))
        ->toThrow(InvalidFaultConfiguration::class, 'Explicit attempt maps may only contain positive integer attempts.')
        ->and(fn () => FaultRule::timeout('', FaultTarget::container('payments')))
        ->toThrow(InvalidFaultConfiguration::class, 'Fault rules require a non-empty name.');
});
