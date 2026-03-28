<?php

namespace MeShaon\LaravelResilience\Tests\Fixtures\Scenarios;

use MeShaon\LaravelResilience\Faults\FaultRule;
use MeShaon\LaravelResilience\Faults\FaultTarget;
use MeShaon\LaravelResilience\Scenarios\ResilienceScenario;
use MeShaon\LaravelResilience\Tests\Fixtures\Search\SearchClient;
use RuntimeException;

final class FailingSearchScenario implements ResilienceScenario
{
    public function description(): string
    {
        return 'Forces the search client to fail and leaves the exception unhandled.';
    }

    /**
     * @return array<int, FaultRule>
     */
    public function faultRules(): array
    {
        return [
            FaultRule::exception(
                'search-down',
                FaultTarget::container(SearchClient::class),
                new RuntimeException('Search is down.')
            ),
        ];
    }

    public function run(): mixed
    {
        return app(SearchClient::class)->search('resilience');
    }
}
