<?php

namespace MeShaon\LaravelResilience;

use MeShaon\LaravelResilience\Support\EnvironmentGuard;

final class LaravelResilience
{
    public function __construct(
        private readonly EnvironmentGuard $environmentGuard
    ) {}

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
}
