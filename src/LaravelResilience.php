<?php

namespace MeShaon\LaravelResilience;

use MeShaon\LaravelResilience\Faults\FaultManager;
use MeShaon\LaravelResilience\Faults\FaultRule;
use MeShaon\LaravelResilience\Faults\FaultScope;
use MeShaon\LaravelResilience\Faults\FaultTarget;
use MeShaon\LaravelResilience\Support\EnvironmentGuard;

final class LaravelResilience
{
    public function __construct(
        private readonly EnvironmentGuard $environmentGuard,
        private readonly FaultManager $faultManager
    ) {}

    public function activate(FaultRule $rule): FaultRule
    {
        $this->ensureCanActivate(sprintf('Fault rule [%s]', $rule->name()));

        return $this->faultManager->activate($rule);
    }

    /**
     * @return array<int, FaultRule>
     */
    public function activeFaults(): array
    {
        return $this->faultManager->activeRules();
    }

    public function enabled(): bool
    {
        return $this->environmentGuard->enabled();
    }

    public function canActivate(): bool
    {
        return $this->environmentGuard->canActivate();
    }

    public function ensureCanActivate(string $subject = 'Laravel Resilience'): void
    {
        $this->environmentGuard->ensureCanActivate($subject);
    }

    public function currentEnvironment(): string
    {
        return $this->environmentGuard->currentEnvironment();
    }

    public function deactivate(FaultTarget $target): void
    {
        $this->faultManager->deactivate($target);
    }

    public function deactivateAll(): void
    {
        $this->faultManager->deactivateAll();
    }

    public function deactivateScope(FaultScope $scope): void
    {
        $this->faultManager->deactivateScope($scope);
    }

    public function faultFor(FaultTarget $target): ?FaultRule
    {
        return $this->faultManager->ruleFor($target);
    }

    /**
     * @return array{
     *     enabled: bool,
     *     can_activate: bool,
     *     current_environment: string,
     *     blocked_environments: array<int, string>
     * }
     */
    public function activationStatus(): array
    {
        return $this->environmentGuard->activationStatus();
    }

    public function shouldActivate(FaultTarget $target, int $attempt): bool
    {
        return $this->faultManager->shouldActivate($target, $attempt);
    }

    public function triggeredFault(FaultTarget $target, int $attempt): ?FaultRule
    {
        return $this->faultManager->ruleForAttempt($target, $attempt);
    }
}
