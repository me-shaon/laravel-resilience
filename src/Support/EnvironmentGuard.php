<?php

namespace MeShaon\LaravelResilience\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use MeShaon\LaravelResilience\Exceptions\ActivationNotAllowed;

final class EnvironmentGuard
{
    public function __construct(
        private readonly Repository $config,
        private readonly Application $app
    ) {}

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
        return [
            'enabled' => $this->enabled(),
            'can_activate' => $this->canActivate(),
            'current_environment' => $this->currentEnvironment(),
            'blocked_environments' => $this->blockedEnvironments(),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function blockedEnvironments(): array
    {
        return array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $environment): string => trim((string) $environment),
                (array) $this->config->get('resilience.blocked_environments', [])
            ),
            static fn (string $environment): bool => $environment !== ''
        )));
    }

    public function canActivate(): bool
    {
        return $this->activationException() === null;
    }

    public function currentEnvironment(): string
    {
        return (string) $this->app->environment();
    }

    public function enabled(): bool
    {
        return (bool) $this->config->get('resilience.enabled', true);
    }

    public function ensureCanActivate(string $subject = 'Laravel Resilience'): void
    {
        $exception = $this->activationException($subject);

        if ($exception !== null) {
            throw $exception;
        }
    }

    private function activationException(string $subject = 'Laravel Resilience'): ?ActivationNotAllowed
    {
        if (! $this->enabled()) {
            return ActivationNotAllowed::becauseDisabled($subject);
        }

        $blockedEnvironments = $this->blockedEnvironments();

        if ($blockedEnvironments === []) {
            return null;
        }

        if (! in_array($this->currentEnvironment(), $blockedEnvironments, true)) {
            return null;
        }

        return ActivationNotAllowed::forBlockedEnvironment(
            $subject,
            $this->currentEnvironment(),
            $blockedEnvironments
        );
    }
}
