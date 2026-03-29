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
    public function suggest(?string $basePath = null, array $categories = []): SuggestionReport
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
                $this->recommendationFor(
                    $finding,
                    $rule['recommendation'],
                    $analysis['assessment'],
                    $analysis['missing'],
                    $analysis['evidence']
                ),
                $finding,
                $analysis['evidence'],
                $analysis['missing']
            );
        }

        return new SuggestionReport($discoveryReport->basePath(), $suggestions);
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

            foreach ($referencePatterns as $pattern) {
                if ($this->matches($contents, $pattern)) {
                    return true;
                }
            }
        }

        return false;
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
