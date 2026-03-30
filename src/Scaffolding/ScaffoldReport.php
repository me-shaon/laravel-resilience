<?php

namespace MeShaon\LaravelResilience\Scaffolding;

final class ScaffoldReport
{
    /**
     * @param  array<int, ScaffoldedItem>  $items
     */
    public function __construct(
        private readonly string $outputDirectory,
        private readonly string $manifestPath,
        private readonly ScaffoldMode $mode,
        private readonly string $format,
        private readonly bool $dryRun,
        private readonly array $items,
    ) {}

    public function dryRun(): bool
    {
        return $this->dryRun;
    }

    public function format(): string
    {
        return $this->format;
    }

    /**
     * @return array<int, ScaffoldedItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function manifestPath(): string
    {
        return $this->manifestPath;
    }

    public function mode(): ScaffoldMode
    {
        return $this->mode;
    }

    public function outputDirectory(): string
    {
        return $this->outputDirectory;
    }

    public function total(): int
    {
        return count($this->items());
    }

    public function countByStatus(string $status): int
    {
        return count(array_filter(
            $this->items(),
            static fn (ScaffoldedItem $item): bool => $item->status() === $status
        ));
    }
}
