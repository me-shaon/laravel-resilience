<?php

namespace MeShaon\LaravelResilience\Scenarios;

use DateTimeImmutable;
use Illuminate\Contracts\Container\Container;
use MeShaon\LaravelResilience\Exceptions\InvalidScenarioConfiguration;
use MeShaon\LaravelResilience\Exceptions\ScenarioNotFound;
use MeShaon\LaravelResilience\LaravelResilience;
use Psr\Log\LoggerInterface;
use Stringable;
use Throwable;

final class ScenarioRunner
{
    public function __construct(
        private readonly Container $container,
        private readonly LaravelResilience $resilience,
        private readonly LoggerInterface $logger
    ) {}

    public function run(string $name): ScenarioRunReport
    {
        $normalizedName = trim($name);

        if ($normalizedName === '') {
            throw InvalidScenarioConfiguration::because('Scenario names must be non-empty.');
        }

        $this->resilience->ensureCanActivate(sprintf('Scenario [%s]', $normalizedName));

        $scenario = $this->resolve($normalizedName);
        $startedAt = new DateTimeImmutable;
        $activatedFaults = [];
        $result = null;
        $exception = null;

        foreach ($scenario->faultRules() as $rule) {
            $this->resilience->activate($rule);
            $activatedFaults[] = $rule->name();
        }

        try {
            $result = $this->normalize($scenario->run());
        } catch (Throwable $throwable) {
            $exception = $throwable;
        } finally {
            $this->resilience->deactivateAll();
        }

        $report = new ScenarioRunReport(
            $normalizedName,
            $scenario->description(),
            $this->resilience->currentEnvironment(),
            $exception === null ? 'success' : 'failed',
            $activatedFaults,
            $startedAt,
            new DateTimeImmutable,
            $result,
            $exception
        );

        $this->log($report);

        return $report;
    }

    private function configuredScenario(string $name): mixed
    {
        $configuredScenarios = config('resilience.scenarios', []);

        if (! is_array($configuredScenarios) || ! array_key_exists($name, $configuredScenarios)) {
            throw ScenarioNotFound::named($name);
        }

        return $configuredScenarios[$name];
    }

    private function log(ScenarioRunReport $report): void
    {
        $level = $report->successful() ? 'info' : 'error';

        $this->logger->{$level}('resilience.scenario_ran', $report->toArray());
    }

    private function normalize(mixed $value): mixed
    {
        if (is_null($value) || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
        }

        if ($value instanceof \JsonSerializable) {
            return $this->normalize($value->jsonSerialize());
        }

        if (method_exists($value, 'toArray')) {
            /** @var mixed $normalized */
            $normalized = $value->toArray();

            return $this->normalize($normalized);
        }

        if (is_object($value)) {
            return [
                'type' => 'object',
                'class' => $value::class,
            ];
        }

        return $value;
    }

    private function resolve(string $name): ResilienceScenario
    {
        $configuredScenario = $this->configuredScenario($name);

        $scenario = match (true) {
            $configuredScenario instanceof ResilienceScenario => $configuredScenario,
            is_string($configuredScenario) => $this->container->make($configuredScenario),
            is_array($configuredScenario) && is_string($configuredScenario['class'] ?? null) => $this->container->make($configuredScenario['class']),
            default => throw InvalidScenarioConfiguration::because(sprintf(
                'Scenario [%s] must be configured as a scenario class, scenario instance, or array with a [class] entry.',
                $name
            )),
        };

        if (! $scenario instanceof ResilienceScenario) {
            throw InvalidScenarioConfiguration::because(sprintf(
                'Configured scenario [%s] must implement [%s].',
                $name,
                ResilienceScenario::class
            ));
        }

        return $scenario;
    }
}
