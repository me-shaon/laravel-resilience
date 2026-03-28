<?php

namespace MeShaon\LaravelResilience\Scenarios;

use MeShaon\LaravelResilience\Faults\FaultRule;

interface ResilienceScenario
{
    public function description(): string;

    /**
     * @return array<int, FaultRule>
     */
    public function faultRules(): array;

    public function run(): mixed;
}
