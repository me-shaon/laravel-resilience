<?php

namespace MeShaon\LaravelResilience\Discovery;

use JsonSerializable;

final class DiscoveryReport implements JsonSerializable
{
    /**
     * @param  array<int, DiscoveryFinding>  $findings
     */
    public function __construct(
        private readonly string $basePath,
        private readonly int $filesScanned,
        private readonly array $findings
    ) {}

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function filesScanned(): int
    {
        return $this->filesScanned;
    }

    /**
     * @return array<int, DiscoveryFinding>
     */
    public function findings(): array
    {
        return $this->findings;
    }

    /**
     * @return array<string, array<int, DiscoveryFinding>>
     */
    public function groupedFindings(): array
    {
        $grouped = [];

        foreach ($this->findings() as $finding) {
            $grouped[$finding->category()][] = $finding;
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * @return array{
     *     base_path: string,
     *     files_scanned: int,
     *     findings: array<int, array{
     *         category: string,
     *         summary: string,
     *         absolute_path: string,
     *         relative_path: string,
     *         line: int,
     *         excerpt: string
     *     }>
     * }
     */
    public function toArray(): array
    {
        return [
            'base_path' => $this->basePath(),
            'files_scanned' => $this->filesScanned(),
            'findings' => array_map(
                static fn (DiscoveryFinding $finding): array => $finding->toArray(),
                $this->findings()
            ),
        ];
    }

    /**
     * @return array{
     *     base_path: string,
     *     files_scanned: int,
     *     findings: array<int, array{
     *         category: string,
     *         summary: string,
     *         absolute_path: string,
     *         relative_path: string,
     *         line: int,
     *         excerpt: string
     *     }>
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
