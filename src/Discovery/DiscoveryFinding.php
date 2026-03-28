<?php

namespace MeShaon\LaravelResilience\Discovery;

final class DiscoveryFinding
{
    public function __construct(
        private readonly string $category,
        private readonly string $summary,
        private readonly string $absolutePath,
        private readonly string $relativePath,
        private readonly int $line,
        private readonly string $excerpt
    ) {}

    public function absolutePath(): string
    {
        return $this->absolutePath;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function excerpt(): string
    {
        return $this->excerpt;
    }

    public function line(): int
    {
        return $this->line;
    }

    public function relativePath(): string
    {
        return $this->relativePath;
    }

    public function summary(): string
    {
        return $this->summary;
    }

    /**
     * @return array{
     *     category: string,
     *     summary: string,
     *     absolute_path: string,
     *     relative_path: string,
     *     line: int,
     *     excerpt: string
     * }
     */
    public function toArray(): array
    {
        return [
            'category' => $this->category(),
            'summary' => $this->summary(),
            'absolute_path' => $this->absolutePath(),
            'relative_path' => $this->relativePath(),
            'line' => $this->line(),
            'excerpt' => $this->excerpt(),
        ];
    }
}
