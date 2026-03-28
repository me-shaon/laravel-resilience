<?php

namespace MeShaon\LaravelResilience\Exceptions;

use InvalidArgumentException;

final class ScenarioNotFound extends InvalidArgumentException
{
    public static function named(string $name): self
    {
        return new self(sprintf('Resilience scenario [%s] is not configured.', $name));
    }
}
