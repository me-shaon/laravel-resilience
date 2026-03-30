<?php

namespace MeShaon\LaravelResilience\Suggestions;

use JsonSerializable;

final class SuggestionReport implements JsonSerializable
{
    /**
     * @param  array<int, ResilienceSuggestion>  $suggestions
     */
    public function __construct(
        private readonly string $basePath,
        private readonly array $suggestions
    ) {}

    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * @return array<string, array<int, ResilienceSuggestion>>
     */
    public function groupedSuggestions(): array
    {
        $grouped = [];

        foreach ($this->suggestions() as $suggestion) {
            $grouped[$suggestion->category()][] = $suggestion;
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * @return array<int, ResilienceSuggestion>
     */
    public function suggestions(): array
    {
        return $this->suggestions;
    }

    /**
     * @return array{
     *     base_path: string,
     *     suggestions: array<int, array{
     *         category: string,
     *         severity: string,
     *         assessment: string,
     *         action: string,
     *         recommendation: string,
     *         evidence: array<int, string>,
     *         missing_signals: array<int, string>,
     *         line_numbers: array<int, int>,
     *         occurrence_count: int,
     *         finding: array{
     *             category: string,
     *             summary: string,
     *             absolute_path: string,
     *             relative_path: string,
     *             line: int,
     *             excerpt: string
     *         }
     *     }>
     * }
     */
    public function toArray(): array
    {
        return [
            'base_path' => $this->basePath(),
            'suggestions' => array_map(
                static fn (ResilienceSuggestion $suggestion): array => $suggestion->toArray(),
                $this->suggestions()
            ),
        ];
    }

    /**
     * @return array{
     *     base_path: string,
     *     suggestions: array<int, array{
     *         category: string,
     *         severity: string,
     *         assessment: string,
     *         action: string,
     *         recommendation: string,
     *         evidence: array<int, string>,
     *         missing_signals: array<int, string>,
     *         line_numbers: array<int, int>,
     *         occurrence_count: int,
     *         finding: array{
     *             category: string,
     *             summary: string,
     *             absolute_path: string,
     *             relative_path: string,
     *             line: int,
     *             excerpt: string
     *         }
     *     }>
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
