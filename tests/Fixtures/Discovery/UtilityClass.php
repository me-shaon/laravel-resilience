<?php

namespace MeShaon\LaravelResilience\Tests\Fixtures\Discovery;

final class UtilityClass
{
    public function normalize(string $value): string
    {
        return trim(strtolower($value));
    }
}
