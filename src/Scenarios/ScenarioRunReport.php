<?php

namespace MeShaon\LaravelResilience\Scenarios;

use DateTimeImmutable;
use JsonSerializable;
use Throwable;

final class ScenarioRunReport implements JsonSerializable
{
    /**
     * @param  array<int, string>  $activatedFaults
     */
    public function __construct(
        private readonly string $name,
        private readonly string $description,
        private readonly string $environment,
        private readonly string $status,
        private readonly array $activatedFaults,
        private readonly DateTimeImmutable $startedAt,
        private readonly DateTimeImmutable $finishedAt,
        private readonly mixed $result = null,
        private readonly ?Throwable $exception = null
    ) {}

    /**
     * @return array<int, string>
     */
    public function activatedFaults(): array
    {
        return $this->activatedFaults;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function durationInMilliseconds(): int
    {
        return max(
            0,
            (int) (($this->finishedAt->format('U.u') - $this->startedAt->format('U.u')) * 1000)
        );
    }

    public function dryRun(): bool
    {
        return $this->status === 'dry-run';
    }

    public function environment(): string
    {
        return $this->environment;
    }

    public function exceptionClass(): ?string
    {
        return $this->exception?->getMessage() !== null
            ? $this->exception::class
            : null;
    }

    public function exceptionMessage(): ?string
    {
        return $this->exception?->getMessage();
    }

    public function finishedAt(): DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function result(): mixed
    {
        return $this->result;
    }

    public function startedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function successful(): bool
    {
        return $this->status === 'success';
    }

    /**
     * @return array{
     *     name: string,
     *     description: string,
     *     environment: string,
     *     status: string,
     *     activated_faults: array<int, string>,
     *     duration_ms: int,
     *     started_at: string,
     *     finished_at: string,
     *     result: mixed,
     *     exception: array{class: string|null, message: string|null}|null
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'environment' => $this->environment(),
            'status' => $this->status(),
            'activated_faults' => $this->activatedFaults(),
            'duration_ms' => $this->durationInMilliseconds(),
            'started_at' => $this->startedAt()->format(DATE_ATOM),
            'finished_at' => $this->finishedAt()->format(DATE_ATOM),
            'result' => $this->result(),
            'exception' => $this->exception === null ? null : [
                'class' => $this->exceptionClass(),
                'message' => $this->exceptionMessage(),
            ],
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     description: string,
     *     environment: string,
     *     status: string,
     *     activated_faults: array<int, string>,
     *     duration_ms: int,
     *     started_at: string,
     *     finished_at: string,
     *     result: mixed,
     *     exception: array{class: string|null, message: string|null}|null
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
