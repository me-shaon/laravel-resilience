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

    public function allowsNonLocalScenarioRuns(): bool
    {
        return (bool) $this->config->get('resilience.scenario_runner.allow_non_local', false);
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

    public function ensureCanRunScenario(
        string $subject,
        bool $confirmedNonLocal = false,
        bool $dryRun = false
    ): void {
        if ($dryRun) {
            return;
        }

        $this->ensureCanActivate($subject);

        if ($this->runsSafelyInCurrentEnvironment()) {
            return;
        }

        if (! $this->allowsNonLocalScenarioRuns()) {
            throw ActivationNotAllowed::becauseNonLocalExecutionRequiresConfig(
                $subject,
                $this->currentEnvironment(),
                $this->safeScenarioEnvironments()
            );
        }

        if (! $confirmedNonLocal) {
            throw ActivationNotAllowed::becauseNonLocalExecutionRequiresConfirmation(
                $subject,
                $this->currentEnvironment()
            );
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

    private function runsSafelyInCurrentEnvironment(): bool
    {
        return in_array($this->currentEnvironment(), $this->safeScenarioEnvironments(), true);
    }

    /**
     * @return array<int, string>
     */
    private function safeScenarioEnvironments(): array
    {
        return array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $environment): string => trim((string) $environment),
                (array) $this->config->get('resilience.scenario_runner.safe_environments', ['local', 'testing'])
            ),
            static fn (string $environment): bool => $environment !== ''
        )));
    }
}
