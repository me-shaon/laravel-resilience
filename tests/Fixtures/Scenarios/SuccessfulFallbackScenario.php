<?php

namespace MeShaon\LaravelResilience\Tests\Fixtures\Scenarios;

use MeShaon\LaravelResilience\Faults\FaultRule;
use MeShaon\LaravelResilience\Faults\FaultTarget;
use MeShaon\LaravelResilience\Scenarios\ResilienceScenario;
use MeShaon\LaravelResilience\Tests\Fixtures\Payments\PaymentGateway;
use RuntimeException;

final class SuccessfulFallbackScenario implements ResilienceScenario
{
    public function description(): string
    {
        return 'Forces the payment gateway to timeout and reports the fallback path.';
    }

    /**
     * @return array<int, FaultRule>
     */
    public function faultRules(): array
    {
        return [
            FaultRule::timeout('payment-timeout', FaultTarget::container(PaymentGateway::class)),
        ];
    }

    public function run(): array
    {
        try {
            app(PaymentGateway::class)->charge(500);

            return [
                'fallback_used' => false,
            ];
        } catch (RuntimeException $exception) {
            return [
                'fallback_used' => true,
                'message' => $exception->getMessage(),
            ];
        }
    }
}
