<?php

namespace MeShaon\LaravelResilience\Exceptions;

use InvalidArgumentException;

final class InvalidScenarioConfiguration extends InvalidArgumentException
{
    public static function because(string $message): self
    {
        return new self($message);
    }
}
