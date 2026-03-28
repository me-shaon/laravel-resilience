<?php

namespace MeShaon\LaravelResilience\Exceptions;

use InvalidArgumentException;

final class InvalidFaultConfiguration extends InvalidArgumentException
{
    public static function because(string $message): self
    {
        return new self($message);
    }
}
