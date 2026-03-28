<?php

namespace MeShaon\LaravelResilience\Facades;

use Illuminate\Support\Facades\Facade;
use MeShaon\LaravelResilience\Faults\FaultRule;
use MeShaon\LaravelResilience\Faults\FaultScope;
use MeShaon\LaravelResilience\Faults\FaultTarget;
use MeShaon\LaravelResilience\LaravelResilience;

/**
 * @see LaravelResilience
 *
 * @method static FaultRule activate(FaultRule $rule)
 * @method static array<int, FaultRule> activeFaults()
 * @method static bool enabled()
 * @method static bool canActivate()
 * @method static void ensureCanActivate(string $subject = 'Laravel Resilience')
 * @method static string currentEnvironment()
 * @method static void deactivate(FaultTarget $target)
 * @method static void deactivateAll()
 * @method static void deactivateScope(FaultScope $scope)
 * @method static ?FaultRule faultFor(FaultTarget $target)
 * @method static array{
 *     enabled: bool,
 *     can_activate: bool,
 *     current_environment: string,
 *     blocked_environments: array<int, string>
 * } activationStatus()
 * @method static bool shouldActivate(FaultTarget $target, int $attempt)
 * @method static ?FaultRule triggeredFault(FaultTarget $target, int $attempt)
 */
class Resilience extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'resilience';
    }
}
