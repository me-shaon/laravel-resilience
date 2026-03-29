<?php

namespace MeShaon\LaravelResilience;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use MeShaon\LaravelResilience\Faults\FaultManager;
use MeShaon\LaravelResilience\Faults\FaultRule;
use MeShaon\LaravelResilience\Faults\FaultScope;
use MeShaon\LaravelResilience\Faults\FaultTarget;
use MeShaon\LaravelResilience\Faults\Injectors\ContainerFaultInjector;
use MeShaon\LaravelResilience\Support\EnvironmentGuard;
use PHPUnit\Framework\Assert;

final class LaravelResilience
{
    /**
     * @var array<string, array{facade: class-string, accessor: string}>
     */
    private const FACADE_TARGETS = [
        HttpFactory::class => ['facade' => Http::class, 'accessor' => HttpFactory::class],
        'mail.manager' => ['facade' => Mail::class, 'accessor' => 'mail.manager'],
        'cache' => ['facade' => Cache::class, 'accessor' => 'cache'],
        'queue' => ['facade' => Queue::class, 'accessor' => 'queue'],
        'filesystem' => ['facade' => Storage::class, 'accessor' => 'filesystem'],
    ];

    public function __construct(
        private readonly EnvironmentGuard $environmentGuard,
        private readonly FaultManager $faultManager,
        private readonly ContainerFaultInjector $containerFaultInjector
    ) {}

    public function activate(FaultRule $rule): FaultRule
    {
        $this->ensureCanActivate(sprintf('Fault rule [%s]', $rule->name()));
        $this->containerFaultInjector->activate($rule);
        $this->refreshFacadeTarget($rule->target());

        return $this->faultManager->activate($rule);
    }

    /**
     * @return array<int, FaultRule>
     */
    public function activeFaults(): array
    {
        return $this->faultManager->activeRules();
    }

    public function enabled(): bool
    {
        return $this->environmentGuard->enabled();
    }

    public function canActivate(): bool
    {
        return $this->environmentGuard->canActivate();
    }

    public function cache(?string $store = null): ContainerFaultBuilder
    {
        return $this->for($store === null ? 'cache' : sprintf('cache::%s', $store));
    }

    public function assertDegradedButSuccessful(TestResponse $response, ?callable $assertDegradedSignal = null): void
    {
        $response->assertSuccessful();

        if ($assertDegradedSignal === null) {
            return;
        }

        $result = $assertDegradedSignal($response);

        if (is_bool($result)) {
            Assert::assertTrue($result, 'Expected degraded response signal assertion to pass.');
        }
    }

    public function assertEventDispatched(string $event, ?callable $callback = null, ?int $times = null): void
    {
        Event::assertDispatched($event, $callback);

        if ($times !== null) {
            Event::assertDispatchedTimes($event, $times);
        }
    }

    public function assertFallbackUsed(mixed $actual, mixed $expected, string $description = 'fallback value'): void
    {
        Assert::assertEquals(
            $expected,
            $actual,
            sprintf('Expected %s to match the configured fallback.', $description)
        );
    }

    public function assertJobDispatched(string $job, ?callable $callback = null, ?int $times = null): void
    {
        Bus::assertDispatched($job, $callback);

        if ($times !== null) {
            Bus::assertDispatchedTimes($job, $times);
        }
    }

    public function assertLogWritten(string $level, string|callable|null $message = null): void
    {
        $logger = Log::getFacadeRoot();

        if (! is_object($logger) || ! method_exists($logger, 'shouldHaveReceived')) {
            Assert::fail('Log assertions require calling Log::spy() before exercising the code under test.');
        }

        $expectation = $logger->shouldHaveReceived($level);

        if (is_string($message)) {
            $expectation->withArgs(
                fn (mixed $loggedMessage, mixed ...$rest): bool => $loggedMessage === $message
            );

            return;
        }

        if ($message !== null) {
            $expectation->withArgs(
                fn (mixed ...$arguments): bool => (bool) $message(...$arguments)
            );
        }
    }

    public function assertNoDuplicateSideEffects(
        int $actualCount,
        int $expectedCount = 1,
        string $description = 'side effect'
    ): void {
        Assert::assertSame(
            $expectedCount,
            $actualCount,
            sprintf(
                'Expected %s to occur exactly %d time(s), but it occurred %d time(s).',
                $description,
                $expectedCount,
                $actualCount
            )
        );
    }

    public function ensureCanActivate(string $subject = 'Laravel Resilience'): void
    {
        $this->environmentGuard->ensureCanActivate($subject);
    }

    public function ensureCanRunScenario(
        string $subject,
        bool $confirmedNonLocal = false,
        bool $dryRun = false
    ): void {
        $this->environmentGuard->ensureCanRunScenario($subject, $confirmedNonLocal, $dryRun);
    }

    public function currentEnvironment(): string
    {
        return $this->environmentGuard->currentEnvironment();
    }

    public function filesystem(?string $disk = null): ContainerFaultBuilder
    {
        return $this->for($disk === null ? 'filesystem' : sprintf('filesystem::%s', $disk));
    }

    public function for(string $abstract): ContainerFaultBuilder
    {
        return new ContainerFaultBuilder($this, $abstract);
    }

    public function http(): ContainerFaultBuilder
    {
        return $this->for(HttpFactory::class);
    }

    public function mail(?string $mailer = null): ContainerFaultBuilder
    {
        return $this->for($mailer === null ? 'mail.manager' : sprintf('mail.manager::%s', $mailer));
    }

    public function queue(?string $connection = null): ContainerFaultBuilder
    {
        return $this->for($connection === null ? 'queue' : sprintf('queue::%s', $connection));
    }

    public function storage(?string $disk = null): ContainerFaultBuilder
    {
        return $this->filesystem($disk);
    }

    public function deactivate(FaultTarget $target): void
    {
        $this->faultManager->deactivate($target);

        if ($target->type() === 'container') {
            $abstract = $this->containerFaultInjector->baseAbstract($target->name());

            if (! $this->faultManager->hasContainerRulesFor($abstract)) {
                $this->containerFaultInjector->restore($abstract);
                $this->refreshFacadeTarget(FaultTarget::container($abstract));
            }
        }
    }

    public function deactivateAll(): void
    {
        $this->containerFaultInjector->restoreAll();
        $this->faultManager->deactivateAll();
        $this->refreshAllFacadeTargets();
    }

    public function deactivateScope(FaultScope $scope): void
    {
        $activeContainerTargets = array_map(
            fn (FaultRule $rule): string => $rule->target()->name(),
            array_filter(
                $this->faultManager->activeRules(),
                fn (FaultRule $rule): bool => $rule->scope() === $scope && $rule->target()->type() === 'container'
            )
        );

        $this->faultManager->deactivateScope($scope);

        foreach ($activeContainerTargets as $abstract) {
            $baseAbstract = $this->containerFaultInjector->baseAbstract($abstract);

            if (! $this->faultManager->hasContainerRulesFor($baseAbstract)) {
                $this->containerFaultInjector->restore($baseAbstract);
                $this->refreshFacadeTarget(FaultTarget::container($baseAbstract));
            }
        }
    }

    public function faultFor(FaultTarget $target): ?FaultRule
    {
        return $this->faultManager->ruleFor($target);
    }

    /**
     * @return array{
     *     enabled: bool,
     *     can_activate: bool,
     *     current_environment: string,
     *     blocked_environments: array<int, string>
     * }
     */
    public function activationStatus(): array
    {
        return $this->environmentGuard->activationStatus();
    }

    public function shouldActivate(FaultTarget $target, int $attempt): bool
    {
        return $this->faultManager->shouldActivate($target, $attempt);
    }

    public function triggeredFault(FaultTarget $target, int $attempt): ?FaultRule
    {
        return $this->faultManager->ruleForAttempt($target, $attempt);
    }

    private function refreshAllFacadeTargets(): void
    {
        foreach (array_keys(self::FACADE_TARGETS) as $abstract) {
            $this->refreshFacadeTarget(FaultTarget::container($abstract));
        }
    }

    private function refreshFacadeTarget(FaultTarget $target): void
    {
        if ($target->type() !== 'container') {
            return;
        }

        $abstract = $this->containerFaultInjector->baseAbstract($target->name());
        $definition = self::FACADE_TARGETS[$abstract] ?? null;

        if ($definition === null) {
            return;
        }

        $definition['facade']::clearResolvedInstance($definition['accessor']);
    }
}
