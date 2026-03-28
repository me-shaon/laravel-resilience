<?php

use MeShaon\LaravelResilience\Facades\Resilience;
use MeShaon\LaravelResilience\Faults\FaultRule;
use MeShaon\LaravelResilience\Faults\FaultScope;
use MeShaon\LaravelResilience\Faults\FaultTarget;

it('supports scoped activation and deactivation', function () {
    $testTarget = FaultTarget::integration('payments');
    $processTarget = FaultTarget::integration('mail');

    $testRule = FaultRule::timeout(
        'payment-timeout',
        $testTarget,
        null,
        FaultScope::Test
    );

    $processRule = FaultRule::exception(
        'mail-failure',
        $processTarget,
        new RuntimeException('Mail failed.'),
        FaultScope::Process
    );

    Resilience::activate($testRule);
    Resilience::activate($processRule);

    expect(Resilience::activeFaults())->toHaveCount(2)
        ->and(Resilience::faultFor($testTarget))->toBe($testRule)
        ->and(Resilience::faultFor($processTarget))->toBe($processRule);

    Resilience::deactivateScope(FaultScope::Test);

    expect(Resilience::faultFor($testTarget))->toBeNull()
        ->and(Resilience::faultFor($processTarget))->toBe($processRule);
});

it('deactivates rules by target and can clear all active faults', function () {
    $paymentTarget = FaultTarget::integration('payments');
    $searchTarget = FaultTarget::integration('search');

    Resilience::activate(FaultRule::timeout('payment-timeout', $paymentTarget));
    Resilience::activate(FaultRule::latency('search-latency', $searchTarget, 250));

    Resilience::deactivate($paymentTarget);

    expect(Resilience::faultFor($paymentTarget))->toBeNull()
        ->and(Resilience::faultFor($searchTarget))->not->toBeNull();

    Resilience::deactivateAll();

    expect(Resilience::activeFaults())->toBe([]);
});

it('evaluates fail-first and recover-after attempts deterministically', function () {
    $target = FaultTarget::integration('payment-gateway');

    Resilience::activate(FaultRule::failFirst('fail-first', $target, 2));

    expect(Resilience::shouldActivate($target, 1))->toBeTrue()
        ->and(Resilience::shouldActivate($target, 2))->toBeTrue()
        ->and(Resilience::shouldActivate($target, 3))->toBeFalse();

    Resilience::deactivateAll();

    Resilience::activate(FaultRule::recoverAfter('recover-after', $target, 3));

    expect(Resilience::shouldActivate($target, 1))->toBeTrue()
        ->and(Resilience::shouldActivate($target, 3))->toBeTrue()
        ->and(Resilience::shouldActivate($target, 4))->toBeFalse();
});
