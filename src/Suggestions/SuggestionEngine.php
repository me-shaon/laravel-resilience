<?php

namespace MeShaon\LaravelResilience\Suggestions;

use Illuminate\Filesystem\Filesystem;
use MeShaon\LaravelResilience\Discovery\DiscoveryFinding;
use MeShaon\LaravelResilience\Discovery\DiscoveryScanner;

final class SuggestionEngine
{
    private readonly string $projectRoot;

    /**
     * @var array<string, array{severity: string, recommendation: string}>
     */
    private const RULES = [
        'http' => [
            'severity' => 'high',
            'recommendation' => 'Wrap this outbound HTTP dependency behind a service boundary and add a resilience scenario or timeout/fallback test around it.',
        ],
        'mail' => [
            'severity' => 'medium',
            'recommendation' => 'Treat this mail send path as a resilience boundary and add a scenario or test for failures, retries, or degraded behavior.',
        ],
        'queue' => [
            'severity' => 'medium',
            'recommendation' => 'Add resilience coverage for this queue dispatch path, especially around duplicate side effects, retries, or degraded execution.',
        ],
        'storage' => [
            'severity' => 'medium',
            'recommendation' => 'Review this storage write path for fallback behavior and consider adding resilience coverage around disk or network failures.',
        ],
        'cache' => [
            'severity' => 'low',
            'recommendation' => 'Check whether this cache usage has a safe fallback path and whether it deserves a degraded-response or cache-miss resilience test.',
        ],
        'client-construction' => [
            'severity' => 'high',
            'recommendation' => 'Consider wrapping this directly constructed client behind a service or contract so fault injection and testing stay manageable.',
        ],
        'concrete-dependency' => [
            'severity' => 'high',
            'recommendation' => 'Consider introducing a contract or service abstraction here so this concrete dependency can be swapped and fault-injected more easily.',
        ],
    ];

    public function __construct(
        private readonly DiscoveryScanner $scanner,
        private readonly Filesystem $files
    ) {
        $this->projectRoot = getcwd() ?: app()->basePath();
    }

    /**
     * @param  array<int, string>  $categories
     */
    public function suggest(?string $basePath = null, array $categories = [], bool $includeCovered = false): SuggestionReport
    {
        $discoveryReport = $this->scanner->scan($basePath, $categories);
        $suggestions = [];

        foreach ($discoveryReport->findings() as $finding) {
            $rule = self::RULES[$finding->category()] ?? null;

            if ($rule === null) {
                continue;
            }

            $analysis = $this->analyze($finding);

            $suggestions[] = new ResilienceSuggestion(
                $finding->category(),
                $rule['severity'],
                $analysis['assessment'],
                $this->actionFor($finding, $analysis['missing']),
                $this->recommendationFor(
                    $finding,
                    $rule['recommendation'],
                    $analysis['assessment'],
                    $analysis['missing'],
                    $analysis['evidence']
                ),
                $finding,
                $analysis['evidence'],
                $analysis['missing'],
                [$finding->line()]
            );
        }

        return new SuggestionReport(
            $discoveryReport->basePath(),
            $this->aggregateSuggestions($suggestions, $includeCovered)
        );
    }

    /**
     * @param  array<int, string>  $missingSignals
     * @param  array<int, string>  $evidence
     */
    private function recommendationFor(
        DiscoveryFinding $finding,
        string $baseRecommendation,
        string $assessment,
        array $missingSignals,
        array $evidence
    ): string {
        $recommendation = match ($finding->category()) {
            'http' => $baseRecommendation.' This is often a good place to extract network logic out of controllers and listeners.',
            'client-construction' => $baseRecommendation.' Direct `new` calls are usually a strong signal that dependency injection would improve resilience testing.',
            default => $baseRecommendation,
        };

        return match ($assessment) {
            'covered' => 'Existing safeguards were detected here. Review whether the current protections are enough before adding more resilience work.',
            'partial' => $recommendation.' Some safeguards are already present, but the missing signals below suggest there is still room to tighten resilience coverage.',
            default => $recommendation,
        };
    }

    /**
     * @param  array<int, string>  $missingSignals
     */
    private function actionFor(DiscoveryFinding $finding, array $missingSignals): string
    {
        if ($finding->category() === 'client-construction' || $finding->category() === 'concrete-dependency') {
            return 'extract dependency seam';
        }

        if (in_array('timeout handling not detected', $missingSignals, true)
            && in_array('local fallback or exception handling not detected', $missingSignals, true)) {
            return 'add timeout and fallback';
        }

        if (in_array('local fallback or exception handling not detected', $missingSignals, true)) {
            return 'add fallback handling';
        }

        if (in_array('duplicate-side-effect guard not detected', $missingSignals, true)) {
            return 'add idempotency guard';
        }

        if (in_array('related tests or resilience scenarios not detected', $missingSignals, true)) {
            return 'add resilience test';
        }

        if (in_array('service or contract abstraction not detected', $missingSignals, true)
            || in_array('contract or interface abstraction not detected', $missingSignals, true)) {
            return 'introduce abstraction';
        }

        return 'review resilience coverage';
    }

    /**
     * @return array{
     *     assessment: string,
     *     evidence: array<int, string>,
     *     missing: array<int, string>
     * }
     */
    private function analyze(DiscoveryFinding $finding): array
    {
        $evidence = [];
        $missing = [];
        $contents = $this->files->get($finding->absolutePath());

        switch ($finding->category()) {
            case 'http':
            case 'mail':
            case 'storage':
            case 'cache':
                if ($this->matches($contents, '/(?:timeout|connectTimeout)\s*\(/')) {
                    $evidence[] = 'timeout handling detected in the same file';
                } else {
                    $missing[] = 'timeout handling not detected';
                }

                if ($this->matches($contents, '/(?:retry|tries|backoff)\s*\(/')) {
                    $evidence[] = 'retry handling detected in the same file';
                }

                if ($this->matches($contents, '/\btry\s*\{[\s\S]*?\}\s*catch\b|\brescue\s*\(/')) {
                    $evidence[] = 'local fallback or exception handling detected';
                } else {
                    $missing[] = 'local fallback or exception handling not detected';
                }

                break;

            case 'queue':
                if ($this->matches($contents, '/(?:ShouldBeUnique|WithoutOverlapping|idempot|uniqueId)/i')) {
                    $evidence[] = 'duplicate-side-effect guard detected';
                } else {
                    $missing[] = 'duplicate-side-effect guard not detected';
                }

                if ($this->matches($contents, '/\btry\s*\{[\s\S]*?\}\s*catch\b|\brescue\s*\(/')) {
                    $evidence[] = 'local fallback or exception handling detected';
                } else {
                    $missing[] = 'local fallback or exception handling not detected';
                }

                break;

            case 'client-construction':
                $missing[] = 'service or contract abstraction not detected';

                break;

            case 'concrete-dependency':
                $missing[] = 'contract or interface abstraction not detected';

                break;
        }

        if ($this->hasRelatedCoverage($finding)) {
            $evidence[] = 'related tests or resilience scenarios detected';
        } else {
            $missing[] = 'related tests or resilience scenarios not detected';
        }

        $assessment = match (true) {
            count($evidence) >= 3 && count($missing) <= 1 => 'covered',
            count($evidence) >= 1 => 'partial',
            default => 'missing',
        };

        return [
            'assessment' => $assessment,
            'evidence' => array_values(array_unique($evidence)),
            'missing' => array_values(array_unique($missing)),
        ];
    }

    private function hasRelatedCoverage(DiscoveryFinding $finding): bool
    {
        $basename = pathinfo($finding->absolutePath(), PATHINFO_FILENAME);
        $referencePatterns = [
            '/\b'.preg_quote($basename, '/').'::class\b/',
            '/\bnew\s+'.preg_quote($basename, '/').'\b/',
            '/\b'.preg_quote($basename, '/').'::\w+\b/',
        ];

        foreach ($this->coverageFiles() as $path) {
            if ($path === $finding->absolutePath()) {
                continue;
            }

            if (! $this->looksLikeCoverageFile($path)) {
                continue;
            }

            $contents = $this->files->get($path);

            if (str_contains($contents, 'Generated scaffold:')) {
                continue;
            }

            foreach ($referencePatterns as $pattern) {
                if ($this->matches($contents, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int, ResilienceSuggestion>  $suggestions
     * @return array<int, ResilienceSuggestion>
     */
    private function aggregateSuggestions(array $suggestions, bool $includeCovered): array
    {
        $grouped = [];

        foreach ($suggestions as $suggestion) {
            if (! $includeCovered && $suggestion->assessment() === 'covered') {
                continue;
            }

            $key = implode('|', [
                $suggestion->category(),
                $suggestion->finding()->relativePath(),
                $suggestion->severity(),
                $suggestion->assessment(),
                $suggestion->action(),
            ]);

            if (! isset($grouped[$key])) {
                $grouped[$key] = $suggestion;

                continue;
            }

            $existing = $grouped[$key];
            $grouped[$key] = new ResilienceSuggestion(
                $existing->category(),
                $existing->severity(),
                $existing->assessment(),
                $existing->action(),
                $existing->recommendation(),
                $existing->finding(),
                array_values(array_unique([...$existing->evidence(), ...$suggestion->evidence()])),
                array_values(array_unique([...$existing->missingSignals(), ...$suggestion->missingSignals()])),
                $this->mergedLineNumbers($existing, $suggestion)
            );
        }

        $aggregated = array_values($grouped);

        usort($aggregated, fn (ResilienceSuggestion $left, ResilienceSuggestion $right): int => $this->sortKey($left) <=> $this->sortKey($right));

        return $aggregated;
    }

    /**
     * @return array{int, int, int, string, int}
     */
    private function sortKey(ResilienceSuggestion $suggestion): array
    {
        $severityRank = match ($suggestion->severity()) {
            'high' => 0,
            'medium' => 1,
            default => 2,
        };

        $assessmentRank = match ($suggestion->assessment()) {
            'missing' => 0,
            'partial' => 1,
            default => 2,
        };

        return [
            $severityRank,
            $assessmentRank,
            -$suggestion->occurrenceCount(),
            $suggestion->finding()->relativePath(),
            min($suggestion->lineNumbers()),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function mergedLineNumbers(ResilienceSuggestion $left, ResilienceSuggestion $right): array
    {
        $lineNumbers = array_values(array_unique([...$left->lineNumbers(), ...$right->lineNumbers()]));
        sort($lineNumbers);

        return $lineNumbers;
    }

    private function looksLikeCoverageFile(string $path): bool
    {
        return str_contains($path, DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR)
            || str_ends_with($path, 'Scenario.php');
    }

    private function matches(string $contents, string $pattern): bool
    {
        return preg_match($pattern, $contents) === 1;
    }

    /**
     * @return array<int, string>
     */
    private function coverageFiles(): array
    {
        $paths = [];

        foreach (['tests', 'app', 'config'] as $directory) {
            $basePath = $this->projectRoot.DIRECTORY_SEPARATOR.$directory;

            if (! $this->files->exists($basePath)) {
                continue;
            }

            foreach ($this->files->allFiles($basePath) as $file) {
                $path = $file->getPathname();

                if (! str_ends_with($path, '.php')) {
                    continue;
                }

                $paths[] = $path;
            }
        }

        sort($paths);

        return $paths;
    }
}
