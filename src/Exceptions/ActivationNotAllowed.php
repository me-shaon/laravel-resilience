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
}
