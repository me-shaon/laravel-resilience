<?php

namespace MeShaon\LaravelResilience;

use MeShaon\LaravelResilience\Faults\FaultRule;
use MeShaon\LaravelResilience\Faults\FaultScope;
use MeShaon\LaravelResilience\Faults\FaultTarget;
use Throwable;

final class ContainerFaultBuilder
{
    public function __construct(
        private readonly LaravelResilience $resilience,
        private readonly string $abstract,
        private readonly FaultScope $scope = FaultScope::Test
    ) {}

    public function exception(Throwable $exception, ?string $name = null): FaultRule
    {
        return $this->resilience->activate(
            FaultRule::exception(
                $name ?? $this->defaultName('exception'),
                FaultTarget::container($this->abstract),
                $exception,
                $this->scope
            )
        );
    }

    public function latency(int $latencyInMilliseconds, ?string $name = null): FaultRule
    {
        return $this->resilience->activate(
            FaultRule::latency(
                $name ?? $this->defaultName('latency'),
                FaultTarget::container($this->abstract),
                $latencyInMilliseconds,
                $this->scope
            )
        );
    }

    public function process(): self
    {
        return new self($this->resilience, $this->abstract, FaultScope::Process);
    }

    public function test(): self
    {
        return new self($this->resilience, $this->abstract, FaultScope::Test);
    }

    public function timeout(?string $name = null, ?Throwable $exception = null): FaultRule
    {
        return $this->resilience->activate(
            FaultRule::timeout(
                $name ?? $this->defaultName('timeout'),
                FaultTarget::container($this->abstract),
                $exception,
                $this->scope
            )
        );
    }

    private function defaultName(string $suffix): string
    {
        return sprintf('%s:%s', $this->abstract, $suffix);
    }
}
