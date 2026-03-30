<?php

namespace MeShaon\LaravelResilience\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use MeShaon\LaravelResilience\Commands\Concerns\InteractsWithReportOutput;
use MeShaon\LaravelResilience\Discovery\DiscoveryScanner;
use MeShaon\LaravelResilience\Reporting\ConsoleOutputMode;
use MeShaon\LaravelResilience\Reporting\HtmlReportGenerator;

class DiscoverResilienceRisksCommand extends Command
{
    use InteractsWithReportOutput;

    protected $signature = 'resilience:discover
        {path? : Base path to scan}
        {--json : Output findings as JSON}
        {--category=* : Limit output to one or more finding categories}
        {--compact : Show a flatter, condensed console report}
        {--view= : Console view mode: compact, default, or verbose}
        {--html= : Write an HTML report to this path or the default build directory}
        {--preview : Print a browser-ready file URL for the HTML report}';

    protected $description = 'Scan the codebase for resilience-relevant patterns';

    public function handle(DiscoveryScanner $scanner, HtmlReportGenerator $htmlReports): int
    {
        try {
            $mode = $this->resolveOutputMode();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

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

            if ($this->wantsHtmlReport()) {
                $htmlPath = $htmlReports->writeDiscoveryReport($report, $mode, $this->htmlReportPathOption());
                $this->announceHtmlReport($htmlPath);
            }

            return self::SUCCESS;
        }

        $groupedFindings = $report->groupedFindings();

        $this->newLine();
        $this->table(
            ['Category', 'Summary', 'Findings'],
            array_map(
                static fn (string $category, array $findings): array => [
                    $category,
                    $findings[0]->summary(),
                    count($findings),
                ],
                array_keys($groupedFindings),
                $groupedFindings
            )
        );

        if ($mode === ConsoleOutputMode::Compact) {
            $this->newLine();
            $this->table(
                ['Category', 'Location'],
                array_map(
                    static fn ($finding): array => [
                        $finding->category(),
                        sprintf('%s:%d', $finding->relativePath(), $finding->line()),
                    ],
                    $report->findings()
                )
            );
        } else {
            foreach ($groupedFindings as $category => $findings) {
                $this->newLine();
                $this->info(sprintf('%s (%d)', $category, count($findings)));
                $this->line(sprintf('Summary: %s', $findings[0]->summary()));
                $this->table(
                    ['Location'],
                    array_map(
                        static fn ($finding): array => [
                            sprintf('%s:%d', $finding->relativePath(), $finding->line()),
                        ],
                        $findings
                    )
                );

                if ($mode === ConsoleOutputMode::Verbose) {
                    $this->line('Excerpts:');

                    foreach ($findings as $finding) {
                        $this->line(sprintf(
                            '- %s: %s',
                            sprintf('%s:%d', $finding->relativePath(), $finding->line()),
                            preg_replace('/\s+/', ' ', trim($finding->excerpt())) ?? trim($finding->excerpt())
                        ));
                    }
                }
            }
        }

        if ($this->wantsHtmlReport()) {
            $htmlPath = $htmlReports->writeDiscoveryReport($report, $mode, $this->htmlReportPathOption());
            $this->announceHtmlReport($htmlPath);
        }

        return self::SUCCESS;
    }
}
