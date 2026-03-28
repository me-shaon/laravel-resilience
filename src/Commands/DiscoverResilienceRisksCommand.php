<?php

namespace MeShaon\LaravelResilience\Commands;

use Illuminate\Console\Command;
use MeShaon\LaravelResilience\Discovery\DiscoveryScanner;

class DiscoverResilienceRisksCommand extends Command
{
    protected $signature = 'resilience:discover
        {path? : Base path to scan}
        {--json : Output findings as JSON}
        {--category=* : Limit output to one or more finding categories}';

    protected $description = 'Scan the codebase for resilience-relevant patterns';

    public function handle(DiscoveryScanner $scanner): int
    {
        $basePath = $this->argument('path');
        $categories = array_values(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), (array) $this->option('category')),
            static fn (string $value): bool => $value !== ''
        ));

        $report = $scanner->scan(
            is_string($basePath) && trim($basePath) !== '' ? $basePath : null,
            $categories
        );

        if ($this->option('json')) {
            $this->line((string) json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Laravel Resilience discovery findings');
        $this->line(sprintf('Scanned path: %s', $report->basePath()));
        $this->line(sprintf('Files scanned: %d', $report->filesScanned()));
        $this->line(sprintf('Findings: %d', count($report->findings())));

        if ($report->findings() === []) {
            $this->info('No resilience-relevant findings were detected.');

            return self::SUCCESS;
        }

        foreach ($report->groupedFindings() as $category => $findings) {
            $this->newLine();
            $this->line(sprintf('%s:', $category));

            foreach ($findings as $finding) {
                $this->line(sprintf(
                    '- %s [%s:%d]',
                    $finding->summary(),
                    $finding->relativePath(),
                    $finding->line()
                ));
            }
        }

        return self::SUCCESS;
    }
}
