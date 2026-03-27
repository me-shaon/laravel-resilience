<?php

namespace MeShaon\LaravelResilience\Facades;

use Illuminate\Support\Facades\Facade;
use MeShaon\LaravelResilience\LaravelResilience;

/**
 * @see LaravelResilience
 *
 * @method static bool enabled()
 * @method static bool canActivate()
 * @method static void ensureCanActivate(string $subject = 'Laravel Resilience')
 * @method static string currentEnvironment()
 * @method static array{
 *     enabled: bool,
 *     can_activate: bool,
 *     current_environment: string,
 *     blocked_environments: array<int, string>
 * } activationStatus()
 */
class Resilience extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'resilience';
    }
}
