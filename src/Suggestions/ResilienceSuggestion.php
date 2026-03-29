<?php

namespace MeShaon\LaravelResilience\Suggestions;

use MeShaon\LaravelResilience\Discovery\DiscoveryFinding;

final class ResilienceSuggestion
{
    /**
     * @param  array<int, string>  $evidence
     * @param  array<int, string>  $missingSignals
     */
    public function __construct(
        private readonly string $category,
        private readonly string $severity,
        private readonly string $assessment,
        private readonly string $recommendation,
        private readonly DiscoveryFinding $finding,
        private readonly array $evidence = [],
        private readonly array $missingSignals = [],
    ) {}

    public function assessment(): string
    {
        return $this->assessment;
    }

    public function category(): string
    {
        return $this->category;
    }

    /**
     * @return array<int, string>
     */
    public function evidence(): array
    {
        return $this->evidence;
    }

    public function finding(): DiscoveryFinding
    {
        return $this->finding;
    }

    /**
     * @return array<int, string>
     */
    public function missingSignals(): array
    {
        return $this->missingSignals;
    }

    public function recommendation(): string
    {
        return $this->recommendation;
    }

    public function severity(): string
    {
        return $this->severity;
    }

    /**
     * @return array{
     *     category: string,
     *     severity: string,
     *     assessment: string,
     *     recommendation: string,
     *     evidence: array<int, string>,
     *     missing_signals: array<int, string>,
     *     finding: array{
     *         category: string,
     *         summary: string,
     *         absolute_path: string,
     *         relative_path: string,
     *         line: int,
     *         excerpt: string
     *     }
     * }
     */
    public function toArray(): array
    {
        return [
            'category' => $this->category(),
            'severity' => $this->severity(),
            'assessment' => $this->assessment(),
            'recommendation' => $this->recommendation(),
            'evidence' => $this->evidence(),
            'missing_signals' => $this->missingSignals(),
            'finding' => $this->finding()->toArray(),
        ];
    }
}
