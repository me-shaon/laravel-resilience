<?php

namespace MeShaon\LaravelResilience\Exceptions;

use RuntimeException;

final class ActivationNotAllowed extends RuntimeException
{
    /**
     * @param  array<int, string>  $blockedEnvironments
     */
    public static function forBlockedEnvironment(
        string $subject,
        string $currentEnvironment,
        array $blockedEnvironments
    ): self {
        return new self(sprintf(
            '%s cannot be activated in the [%s] environment. Blocked environments: [%s].',
            $subject,
            $currentEnvironment,
            implode(', ', $blockedEnvironments)
        ));
    }

    public static function becauseDisabled(string $subject): self
    {
        return new self(sprintf(
            '%s is disabled by the global kill switch [resilience.enabled].',
            $subject
        ));
    }

    /**
     * @param  array<int, string>  $safeEnvironments
     */
    public static function becauseNonLocalExecutionRequiresConfig(
        string $subject,
        string $currentEnvironment,
        array $safeEnvironments
    ): self {
        return new self(sprintf(
            '%s cannot run in the [%s] environment without enabling [resilience.scenario_runner.allow_non_local]. Safe environments: [%s].',
            $subject,
            $currentEnvironment,
            implode(', ', $safeEnvironments)
        ));
    }

    public static function becauseNonLocalExecutionRequiresConfirmation(
        string $subject,
        string $currentEnvironment
    ): self {
        return new self(sprintf(
            '%s requires explicit confirmation for non-local execution in the [%s] environment. Re-run the command with [--confirm-non-local].',
            $subject,
            $currentEnvironment
        ));
    }
}
