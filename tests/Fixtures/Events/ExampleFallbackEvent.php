<?php

namespace MeShaon\LaravelResilience\Tests\Fixtures\Events;

final class ExampleFallbackEvent
{
    public function __construct(public readonly string $source) {}
}
