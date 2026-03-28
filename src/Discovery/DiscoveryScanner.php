<?php

namespace MeShaon\LaravelResilience\Discovery;

use Illuminate\Filesystem\Filesystem;

final class DiscoveryScanner
{
    private readonly string $projectRoot;

    /**
     * @var array<int, array{
     *     category: string,
     *     pattern: string,
     *     summary: string
     * }>
     */
    private const PATTERNS = [
        [
            'category' => 'http',
            'pattern' => '/\bHttp::(get|post|put|patch|delete|send|retry|withToken|withHeaders)\b/',
            'summary' => 'Outbound HTTP call through the Laravel HTTP client.',
        ],
        [
            'category' => 'mail',
            'pattern' => '/\bMail::(to|cc|bcc|send|queue|later|raw|mailer)\b/',
            'summary' => 'Mail send point through the Laravel mail facade.',
        ],
        [
            'category' => 'queue',
            'pattern' => '/\b(?:Queue|Bus)::(push|dispatch|dispatchSync|dispatchNow|chain|batch|connection)\b|\bdispatch\s*\(/',
            'summary' => 'Queue or bus dispatch point.',
        ],
        [
            'category' => 'storage',
            'pattern' => '/\b(?:Storage|File)::(put|get|delete|disk|write|append|copy|move)\b/',
            'summary' => 'Filesystem or storage interaction.',
        ],
        [
            'category' => 'cache',
            'pattern' => '/\bCache::(get|put|remember|rememberForever|store|tags|lock|flexible)\b/',
            'summary' => 'Cache access in application code.',
        ],
        [
            'category' => 'client-construction',
            'pattern' => '/\bnew\s+[A-Z][A-Za-z0-9_\\\\]*(Client|Gateway|Sdk|Adapter)\b(?:\s*\(|\s*;|\s*$)/m',
            'summary' => 'Direct construction of an external-style client.',
        ],
        [
            'category' => 'concrete-dependency',
            'pattern' => '/public function __construct\s*\([^)]*(?:Client|Gateway|Sdk|Adapter)\s+\$/',
            'summary' => 'Constructor appears tightly coupled to a concrete external dependency.',
        ],
    ];

    public function __construct(private readonly Filesystem $files)
    {
        $this->projectRoot = getcwd() ?: app()->basePath();
    }

    /**
     * @param  array<int, string>  $categories
     */
    public function scan(?string $basePath = null, array $categories = []): DiscoveryReport
    {
        $resolvedBasePath = $basePath === null || trim($basePath) === ''
            ? $this->resolveBasePath('app')
            : $this->resolveBasePath($basePath);

        $allowedCategories = array_values(array_unique(array_filter(
            array_map(static fn (string $category): string => trim($category), $categories),
            static fn (string $category): bool => $category !== ''
        )));

        $findings = [];
        $filesScanned = 0;

        foreach ($this->scanableFiles($resolvedBasePath) as $absolutePath) {
            $filesScanned++;

            $contents = $this->files->get($absolutePath);

            foreach ($this->patterns($allowedCategories) as $pattern) {
                if (! preg_match_all($pattern['pattern'], $contents, $matches, PREG_OFFSET_CAPTURE)) {
                    continue;
                }

                foreach ($matches[0] as [$match, $offset]) {
                    $line = $this->lineNumber($contents, (int) $offset);
                    $excerpt = $this->lineContents($contents, $line);

                    $findings[] = new DiscoveryFinding(
                        $pattern['category'],
                        $pattern['summary'],
                        $absolutePath,
                        $this->relativePath($absolutePath),
                        $line,
                        $excerpt
                    );
                }
            }
        }

        usort(
            $findings,
            static fn (DiscoveryFinding $left, DiscoveryFinding $right): int => [$left->relativePath(), $left->line(), $left->category()]
                <=> [$right->relativePath(), $right->line(), $right->category()]
        );

        return new DiscoveryReport($resolvedBasePath, $filesScanned, $findings);
    }

    private function lineContents(string $contents, int $line): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $contents) ?: [];

        return trim((string) ($lines[$line - 1] ?? ''));
    }

    private function lineNumber(string $contents, int $offset): int
    {
        return substr_count(substr($contents, 0, $offset), "\n") + 1;
    }

    /**
     * @param  array<int, string>  $allowedCategories
     * @return array<int, array{
     *     category: string,
     *     pattern: string,
     *     summary: string
     * }>
     */
    private function patterns(array $allowedCategories): array
    {
        if ($allowedCategories === []) {
            return self::PATTERNS;
        }

        return array_values(array_filter(
            self::PATTERNS,
            static fn (array $pattern): bool => in_array($pattern['category'], $allowedCategories, true)
        ));
    }

    private function relativePath(string $absolutePath): string
    {
        return ltrim(str_replace($this->projectRoot, '', $absolutePath), DIRECTORY_SEPARATOR);
    }

    private function resolveBasePath(string $basePath): string
    {
        $candidate = str_starts_with($basePath, DIRECTORY_SEPARATOR)
            ? $basePath
            : $this->projectRoot.DIRECTORY_SEPARATOR.$basePath;

        return rtrim($candidate, DIRECTORY_SEPARATOR);
    }

    /**
     * @return array<int, string>
     */
    private function scanableFiles(string $basePath): array
    {
        if (! $this->files->exists($basePath)) {
            return [];
        }

        $allFiles = array_map(
            static fn (\SplFileInfo $file): string => $file->getPathname(),
            $this->files->allFiles($basePath)
        );

        sort($allFiles);

        return array_values(array_filter(
            $allFiles,
            static fn (string $path): bool => str_ends_with($path, '.php')
        ));
    }
}
