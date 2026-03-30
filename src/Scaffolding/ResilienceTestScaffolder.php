<?php

namespace MeShaon\LaravelResilience\Scaffolding;

use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use MeShaon\LaravelResilience\Suggestions\ResilienceSuggestion;
use MeShaon\LaravelResilience\Suggestions\SuggestionEngine;

final class ResilienceTestScaffolder
{
    private readonly string $projectRoot;

    public function __construct(
        private readonly SuggestionEngine $suggestions,
        private readonly Filesystem $files,
    ) {
        $this->projectRoot = getcwd() ?: app()->basePath();
    }

    /**
     * @param  array<int, string>  $categories
     */
    public function scaffold(
        ?string $basePath = null,
        array $categories = [],
        bool $includeCovered = false,
        ScaffoldMode $mode = ScaffoldMode::Create,
        string $format = 'pest',
        ?string $outputDirectory = null,
        ?string $manifestPath = null,
        bool $dryRun = false,
    ): ScaffoldReport {
        if ($format !== 'pest') {
            throw new InvalidArgumentException('The scaffold format must currently be [pest].');
        }

        $suggestionReport = $this->suggestions->suggest($basePath, $categories, $includeCovered);
        $resolvedOutputDirectory = $this->resolvePath($outputDirectory ?? 'tests/Resilience/Generated');
        $resolvedManifestPath = $this->resolvePath($manifestPath ?? 'build/resilience-scaffold.json');
        $manifest = $this->loadManifest($resolvedManifestPath);
        $items = [];

        foreach ($suggestionReport->suggestions() as $suggestion) {
            $hotspotId = $this->hotspotId($suggestion);
            $outputPath = $this->outputPathFor($resolvedOutputDirectory, $suggestion, $hotspotId);
            $contents = $this->renderPestTest($suggestion, $hotspotId);
            $generatedHash = sha1($contents);
            $decision = $this->decide($mode, $outputPath, $hotspotId, $generatedHash, $manifest);

            $items[] = new ScaffoldedItem(
                $hotspotId,
                $decision['status'],
                $outputPath,
                $decision['reason'],
                $suggestion,
            );

            if ($decision['write'] === false || $dryRun) {
                continue;
            }

            $this->files->ensureDirectoryExists(dirname($outputPath));
            $this->files->put($outputPath, $contents);
            $manifest['entries'][$hotspotId] = [
                'hotspot_id' => $hotspotId,
                'source_path' => $suggestion->finding()->relativePath(),
                'line_numbers' => $suggestion->lineNumbers(),
                'output_path' => $outputPath,
                'generated_hash' => $generatedHash,
                'updated_at' => date(DATE_ATOM),
            ];
        }

        if (! $dryRun) {
            $this->files->ensureDirectoryExists(dirname($resolvedManifestPath));
            $this->files->put($resolvedManifestPath, (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return new ScaffoldReport(
            $resolvedOutputDirectory,
            $resolvedManifestPath,
            $mode,
            $format,
            $dryRun,
            $items,
        );
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array{status: string, reason: string, write: bool}
     */
    private function decide(
        ScaffoldMode $mode,
        string $outputPath,
        string $hotspotId,
        string $generatedHash,
        array $manifest,
    ): array {
        if (! $this->files->exists($outputPath)) {
            return match ($mode) {
                ScaffoldMode::Update => ['status' => 'skipped', 'reason' => 'no generated file exists yet', 'write' => false],
                default => ['status' => 'generated', 'reason' => 'new scaffold file', 'write' => true],
            };
        }

        $currentContents = $this->files->get($outputPath);
        $isManagedFile = str_contains($currentContents, sprintf('@laravel-resilience-hotspot: %s', $hotspotId));
        $manifestEntry = $manifest['entries'][$hotspotId] ?? null;
        $currentHash = sha1($currentContents);
        $customized = is_array($manifestEntry)
            && isset($manifestEntry['generated_hash'])
            && $manifestEntry['generated_hash'] !== $currentHash;

        return match ($mode) {
            ScaffoldMode::Create => ['status' => $customized ? 'customized' : 'skipped', 'reason' => $customized ? 'existing scaffold file was customized' : 'existing scaffold file left untouched', 'write' => false],
            ScaffoldMode::Update => match (true) {
                ! $isManagedFile => ['status' => 'skipped', 'reason' => 'existing file is not a managed scaffold file', 'write' => false],
                $customized => ['status' => 'customized', 'reason' => 'managed scaffold file was customized, so it was not overwritten', 'write' => false],
                $manifestEntry !== null && $manifestEntry['generated_hash'] === $generatedHash => ['status' => 'skipped', 'reason' => 'generated scaffold is already up to date', 'write' => false],
                default => ['status' => 'updated', 'reason' => 'managed scaffold file refreshed', 'write' => true],
            },
            ScaffoldMode::Force => ['status' => 'updated', 'reason' => 'scaffold file overwritten in force mode', 'write' => true],
        };
    }

    private function faultSetupCode(ResilienceSuggestion $suggestion): string
    {
        return match ($suggestion->category()) {
            'http' => '    Resilience::http()->timeout();',
            'mail' => "    Resilience::mail()->exception(new RuntimeException('Mail dependency failed.'));",
            'queue' => "    Resilience::queue()->exception(new RuntimeException('Queue dependency failed.'));",
            'storage' => '    Resilience::storage()->latency(150);',
            'cache' => '    Resilience::cache()->latency(150);',
            default => implode("\n", [
                "    \$target = 'App\\\\Contracts\\\\ExternalDependency';",
                '    // TODO: replace the placeholder target above with the real contract or service boundary.',
                '    Resilience::for($target)->timeout();',
            ]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function loadManifest(string $path): array
    {
        if (! $this->files->exists($path)) {
            return ['entries' => []];
        }

        $decoded = json_decode($this->files->get($path), true);

        return is_array($decoded) ? $decoded : ['entries' => []];
    }

    private function outputPathFor(string $outputDirectory, ResilienceSuggestion $suggestion, string $hotspotId): string
    {
        return $outputDirectory
            .DIRECTORY_SEPARATOR.$suggestion->category()
            .DIRECTORY_SEPARATOR.$this->studly($hotspotId).'Test.php';
    }

    private function renderPestTest(ResilienceSuggestion $suggestion, string $hotspotId): string
    {
        $usesRuntimeException = in_array($suggestion->category(), ['mail', 'queue'], true);
        $imports = [
            'use MeShaon\\LaravelResilience\\Facades\\Resilience;',
        ];

        if ($usesRuntimeException) {
            $imports[] = 'use RuntimeException;';
        }

        sort($imports);

        $metadata = [
            sprintf('// @laravel-resilience-hotspot: %s', $hotspotId),
            sprintf('// @laravel-resilience-source: %s:%s', $suggestion->finding()->relativePath(), implode(',', $suggestion->lineNumbers())),
            sprintf('// @laravel-resilience-category: %s', $suggestion->category()),
            sprintf('// @laravel-resilience-action: %s', $suggestion->action()),
            sprintf('// @laravel-resilience-assessment: %s', $suggestion->assessment()),
        ];

        $commentLines = array_map(
            static fn (string $line): string => sprintf('// %s', $line),
            array_merge(
                ['Recommendation: '.$suggestion->recommendation()],
                $suggestion->missingSignals() === [] ? [] : ['Missing signals: '.implode('; ', $suggestion->missingSignals())],
                $suggestion->evidence() === [] ? [] : ['Detected evidence: '.implode('; ', $suggestion->evidence())]
            )
        );

        return implode("\n", array_filter([
            '<?php',
            '',
            implode("\n", $imports),
            '',
            implode("\n", $metadata),
            implode("\n", $commentLines),
            '',
            sprintf(
                "it('scaffolds resilience coverage for %s', function () {",
                addslashes(sprintf('%s at %s', $suggestion->category(), $this->hotspotLabel($suggestion)))
            ),
            '    // TODO: replace the generated fault setup if this hotspot is better exercised through a contract or named Laravel target.',
            $this->faultSetupCode($suggestion),
            '',
            '    // TODO: execute the real application flow that reaches this hotspot.',
            '    $result = null;',
            '',
            '    // TODO: replace this placeholder assertion with checks for fallback behavior, degraded responses, logs, retries, jobs, or side effects.',
            '    expect($result)->not->toBeNull();',
            '',
            '    Resilience::deactivateAll();',
            "})->skip('Generated scaffold: replace placeholders with the real application flow and assertions.');",
            '',
        ]));
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            return $path;
        }

        return $this->projectRoot.DIRECTORY_SEPARATOR.$path;
    }

    private function hotspotId(ResilienceSuggestion $suggestion): string
    {
        $parts = [
            $suggestion->category(),
            pathinfo($suggestion->finding()->relativePath(), PATHINFO_FILENAME),
            $suggestion->action(),
            substr(sha1($this->hotspotLabel($suggestion)), 0, 8),
        ];

        $slug = strtolower(implode('-', $parts));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? strtolower(implode('-', $parts));

        return trim($slug, '-');
    }

    private function hotspotLabel(ResilienceSuggestion $suggestion): string
    {
        return sprintf(
            '%s:%s',
            $suggestion->finding()->relativePath(),
            implode(',', $suggestion->lineNumbers())
        );
    }

    private function studly(string $value): string
    {
        $parts = preg_split('/[^A-Za-z0-9]+/', $value) ?: [];

        return implode('', array_map(
            static fn (string $part): string => ucfirst(strtolower($part)),
            array_filter($parts, static fn (string $part): bool => $part !== '')
        ));
    }
}
