<?php

namespace MeShaon\LaravelResilience\Faults;

use MeShaon\LaravelResilience\Exceptions\InvalidFaultConfiguration;

final class FaultTarget
{
    private function __construct(
        private readonly string $type,
        private readonly string $name
    ) {}

    public static function for(string $type, string $name): self
    {
        $normalizedType = trim($type);
        $normalizedName = trim($name);

        if ($normalizedType === '') {
            throw InvalidFaultConfiguration::because('Fault targets require a non-empty type.');
        }

        if ($normalizedName === '') {
            throw InvalidFaultConfiguration::because('Fault targets require a non-empty name.');
        }

        return new self($normalizedType, $normalizedName);
    }

    public static function container(string $abstract): self
    {
        return self::for('container', $abstract);
    }

    public static function integration(string $name): self
    {
        return self::for('integration', $name);
    }

    public function key(): string
    {
        return $this->type.':'.$this->name;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): string
    {
        return $this->type;
    }
}
