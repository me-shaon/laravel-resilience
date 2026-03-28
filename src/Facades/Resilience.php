<?php

namespace MeShaon\LaravelResilience\Facades;

use Illuminate\Support\Facades\Facade;
use MeShaon\LaravelResilience\ContainerFaultBuilder;
use MeShaon\LaravelResilience\Faults\FaultRule;
use MeShaon\LaravelResilience\Faults\FaultScope;
use MeShaon\LaravelResilience\Faults\FaultTarget;
use MeShaon\LaravelResilience\LaravelResilience;

/**
 * @see LaravelResilience
 *
 * @method static FaultRule activate(FaultRule $rule)
 * @method static array<int, FaultRule> activeFaults()
 * @method static ContainerFaultBuilder cache(?string $store = null)
 * @method static bool enabled()
 * @method static bool canActivate()
 * @method static void ensureCanActivate(string $subject = 'Laravel Resilience')
 * @method static string currentEnvironment()
 * @method static ContainerFaultBuilder filesystem(?string $disk = null)
 * @method static ContainerFaultBuilder for(string $abstract)
 * @method static ContainerFaultBuilder http()
 * @method static ContainerFaultBuilder mail(?string $mailer = null)
 * @method static ContainerFaultBuilder queue(?string $connection = null)
 * @method static void deactivate(FaultTarget $target)
 * @method static void deactivateAll()
 * @method static void deactivateScope(FaultScope $scope)
 * @method static ContainerFaultBuilder storage(?string $disk = null)
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
