<?php

namespace MeShaon\LaravelResilience\Facades;

use Illuminate\Support\Facades\Facade;
use MeShaon\LaravelResilience\LaravelResilience;

/**
 * @see LaravelResilience
 */
class Resilience extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'resilience';
    }
}
