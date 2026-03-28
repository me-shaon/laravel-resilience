<?php

namespace MeShaon\LaravelResilience\Faults;

use MeShaon\LaravelResilience\Exceptions\InvalidFaultConfiguration;
use RuntimeException;
use Throwable;

final class FaultRule
{
    /**
     * @param  array<int, int>  $activeAttempts
     */
    private function __construct(
        private readonly string $name,
        private readonly FaultTarget $target,
        private readonly FaultType $type,
        private readonly FaultScope $scope = FaultScope::Test,
        private readonly ?Throwable $exception = null,
        private readonly ?int $latencyInMilliseconds = null,
        private readonly ?int $attempts = null,
        private readonly ?float $percentage = null,
        private readonly ?string $seed = null,
        private readonly array $activeAttempts = []
    ) {
        if (trim($name) === '') {
            throw InvalidFaultConfiguration::because('Fault rules require a non-empty name.');
        }
    }

    public static function exception(
        string $name,
        FaultTarget $target,
        Throwable $exception,
        FaultScope $scope = FaultScope::Test
    ): self {
        return new self($name, $target, FaultType::Exception, $scope, $exception);
    }

    public static function failFirst(
        string $name,
        FaultTarget $target,
        int $attempts,
        FaultScope $scope = FaultScope::Test
    ): self {
        self::ensurePositiveAttempts($attempts, 'Fail-first faults require at least one attempt.');

        return new self($name, $target, FaultType::FailFirstN, $scope, attempts: $attempts);
    }

    public static function latency(
        string $name,
        FaultTarget $target,
        int $latencyInMilliseconds,
        FaultScope $scope = FaultScope::Test
    ): self {
        if ($latencyInMilliseconds < 1) {
            throw InvalidFaultConfiguration::because('Latency faults require a positive number of milliseconds.');
        }

        return new self(
            $name,
            $target,
            FaultType::Latency,
            $scope,
            latencyInMilliseconds: $latencyInMilliseconds
        );
    }

    public static function percentage(
        string $name,
        FaultTarget $target,
        float $percentage,
        string $seed,
        FaultScope $scope = FaultScope::Test
    ): self {
        self::ensureValidPercentage($percentage);

        $normalizedSeed = trim($seed);

        if ($normalizedSeed === '') {
            throw InvalidFaultConfiguration::because('Percentage faults require a non-empty seed.');
        }

        return new self(
            $name,
            $target,
            FaultType::Percentage,
            $scope,
            percentage: $percentage,
            seed: $normalizedSeed
        );
    }

    /**
     * @param  array<int, mixed>  $attempts
     */
    public static function percentageOnAttempts(
        string $name,
        FaultTarget $target,
        array $attempts,
        FaultScope $scope = FaultScope::Test
    ): self {
        $normalizedAttempts = array_values(array_unique(array_map(
            static function (mixed $attempt): int {
                if (! is_int($attempt) || $attempt < 1) {
                    throw InvalidFaultConfiguration::because('Explicit attempt maps may only contain positive integer attempts.');
                }

                return $attempt;
            },
            $attempts
        )));

        sort($normalizedAttempts);

        if ($normalizedAttempts === []) {
            throw InvalidFaultConfiguration::because('Explicit attempt maps must contain at least one attempt.');
        }

        return new self(
            $name,
            $target,
            FaultType::Percentage,
            $scope,
            activeAttempts: $normalizedAttempts
        );
    }

    public static function recoverAfter(
        string $name,
        FaultTarget $target,
        int $attempts,
        FaultScope $scope = FaultScope::Test
    ): self {
        self::ensurePositiveAttempts($attempts, 'Recover-after faults require at least one failing attempt.');

        return new self($name, $target, FaultType::RecoverAfterNAttempts, $scope, attempts: $attempts);
    }

    public static function timeout(
        string $name,
        FaultTarget $target,
        ?Throwable $exception = null,
        FaultScope $scope = FaultScope::Test
    ): self {
        return new self(
            $name,
            $target,
            FaultType::Timeout,
            $scope,
            $exception ?? new RuntimeException('Operation timed out.')
        );
    }

    public function activatesOn(int $attempt): bool
    {
        self::ensurePositiveAttempts($attempt, 'Fault attempts must start at 1.');

        return match ($this->type) {
            FaultType::Exception,
            FaultType::Timeout,
            FaultType::Latency => true,
            FaultType::FailFirstN,
            FaultType::RecoverAfterNAttempts => $attempt <= $this->attempts,
            FaultType::Percentage => $this->activatesPercentageFaultOn($attempt),
        };
    }

    /**
     * @return array<int, int>
     */
    public function activeAttempts(): array
    {
        return $this->activeAttempts;
    }

    public function attempts(): ?int
    {
        return $this->attempts;
    }

    public function exceptionToThrow(): ?Throwable
    {
        return $this->exception;
    }

    public function latencyInMilliseconds(): ?int
    {
        return $this->latencyInMilliseconds;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function percentageRate(): ?float
    {
        return $this->percentage;
    }

    public function scope(): FaultScope
    {
        return $this->scope;
    }

    public function seed(): ?string
    {
        return $this->seed;
    }

    public function target(): FaultTarget
    {
        return $this->target;
    }

    public function type(): FaultType
    {
        return $this->type;
    }

    private function activatesPercentageFaultOn(int $attempt): bool
    {
        if ($this->activeAttempts !== []) {
            return in_array($attempt, $this->activeAttempts, true);
        }

        $percentage = $this->percentage ?? 0.0;

        if ($percentage === 0.0) {
            return false;
        }

        if ($percentage === 100.0) {
            return true;
        }

        $bucket = (int) sprintf('%u', crc32($this->seed.'|'.$this->target->key().'|'.$attempt)) % 10000;

        return $bucket < (int) round($percentage * 100);
    }

    private static function ensurePositiveAttempts(int $attempts, string $message): void
    {
        if ($attempts < 1) {
            throw InvalidFaultConfiguration::because($message);
        }
    }

    private static function ensureValidPercentage(float $percentage): void
    {
        if ($percentage < 0 || $percentage > 100) {
            throw InvalidFaultConfiguration::because('Percentage-based faults must be configured between 0 and 100.');
        }
    }
}
