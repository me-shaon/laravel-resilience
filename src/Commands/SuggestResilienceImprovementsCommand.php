<?php

namespace MeShaon\LaravelResilience\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use MeShaon\LaravelResilience\Commands\Concerns\InteractsWithReportOutput;
use MeShaon\LaravelResilience\Reporting\ConsoleOutputMode;
use MeShaon\LaravelResilience\Reporting\HtmlReportGenerator;
use MeShaon\LaravelResilience\Suggestions\SuggestionEngine;

class SuggestResilienceImprovementsCommand extends Command
{
    use InteractsWithReportOutput;

    protected $signature = 'resilience:suggest
        {path? : Base path to scan}
        {--json : Output suggestions as JSON}
        {--category=* : Limit suggestions to one or more categories}
        {--include-covered : Include suggestions that already look covered}
        {--compact : Show a flatter, condensed console report}
        {--view= : Console view mode: compact, default, or verbose}
        {--html= : Write an HTML report to this path or the default build directory}
        {--preview : Print a browser-ready file URL for the HTML report}';

    protected $description = 'Generate resilience improvement suggestions from discovery findings';

    public function handle(SuggestionEngine $engine, HtmlReportGenerator $htmlReports): int
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

        $report = $engine->suggest(
            is_string($basePath) && trim($basePath) !== '' ? $basePath : null,
            $categories,
            (bool) $this->option('include-covered')
        );

        if ($this->option('json')) {
            $this->line((string) json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Laravel Resilience suggestions');
        $this->line(sprintf('Scanned path: %s', $report->basePath()));
        $this->line(sprintf('Suggestions: %d', count($report->suggestions())));

        if ($report->suggestions() === []) {
            $this->info((bool) $this->option('include-covered')
                ? 'No suggestions were generated from the current findings.'
                : 'No actionable suggestions were generated from the current findings. Use [--include-covered] to review already-covered areas.');

            if ($this->wantsHtmlReport()) {
                $htmlPath = $htmlReports->writeSuggestionReport($report, $mode, $this->htmlReportPathOption());
                $this->announceHtmlReport($htmlPath);
            }

            return self::SUCCESS;
        }

        $groupedSuggestions = $report->groupedSuggestions();

        $this->newLine();
        $this->table(
            ['Category', 'Suggestions', 'Risk mix', 'Coverage mix', 'Action mix'],
            array_map(
                fn (string $category, array $suggestions): array => [
                    $category,
                    count($suggestions),
                    $this->summarizeSuggestionField($suggestions, 'severity'),
                    $this->summarizeSuggestionField($suggestions, 'assessment'),
                    $this->summarizeSuggestionField($suggestions, 'action'),
                ],
                array_keys($groupedSuggestions),
                $groupedSuggestions
            )
        );

        if ($mode === ConsoleOutputMode::Compact) {
            $this->newLine();
            $this->table(
                ['Category', 'Severity', 'Assessment', 'Action', 'Hotspot'],
                array_map(
                    fn ($suggestion): array => [
                        $suggestion->category(),
                        $suggestion->severity(),
                        $suggestion->assessment(),
                        $suggestion->action(),
                        $this->hotspotLabel($suggestion),
                    ],
                    $report->suggestions()
                )
            );
        } else {
            foreach ($groupedSuggestions as $category => $suggestions) {
                $this->newLine();
                $this->info(sprintf('%s (%d)', $category, count($suggestions)));
                $this->table(
                    ['Severity', 'Assessment', 'Action', 'Hotspot', 'Recommendation'],
                    array_map(
                        fn ($suggestion): array => [
                            $suggestion->severity(),
                            $suggestion->assessment(),
                            $suggestion->action(),
                            $this->hotspotLabel($suggestion),
                            $suggestion->recommendation(),
                        ],
                        $suggestions
                    )
                );

                $this->line($mode === ConsoleOutputMode::Verbose ? 'Signals:' : 'Next focus:');

                foreach ($suggestions as $suggestion) {
                    $location = $this->hotspotLabel($suggestion);

                    if ($mode !== ConsoleOutputMode::Verbose && $suggestion->missingSignals() === []) {
                        continue;
                    }

                    if ($suggestion->evidence() === [] && $suggestion->missingSignals() === []) {
                        $this->line(sprintf('- %s: no supporting signals were detected.', $location));

                        continue;
                    }

                    $parts = [];

                    if ($mode === ConsoleOutputMode::Verbose && $suggestion->evidence() !== []) {
                        $parts[] = sprintf('Evidence: %s', implode('; ', $suggestion->evidence()));
                    }

                    if ($suggestion->missingSignals() !== []) {
                        $parts[] = sprintf(
                            $mode === ConsoleOutputMode::Verbose ? 'Missing: %s' : '%s',
                            implode('; ', $suggestion->missingSignals())
                        );
                    }

                    if ($mode === ConsoleOutputMode::Verbose) {
                        $parts[] = sprintf(
                            'Excerpt: %s',
                            preg_replace('/\s+/', ' ', trim($suggestion->finding()->excerpt())) ?? trim($suggestion->finding()->excerpt())
                        );
                    }

                    $this->line(sprintf('- %s: %s', $location, implode(' | ', $parts)));
                }
            }
        }

        if ($this->wantsHtmlReport()) {
            $htmlPath = $htmlReports->writeSuggestionReport($report, $mode, $this->htmlReportPathOption());
            $this->announceHtmlReport($htmlPath);
        }

        return self::SUCCESS;
    }

    private function hotspotLabel(mixed $suggestion): string
    {
        return sprintf(
            '%s:%s',
            $suggestion->finding()->relativePath(),
            implode(',', $suggestion->lineNumbers())
        );
    }

    /**
     * @param  array<int, mixed>  $suggestions
     */
    private function summarizeSuggestionField(array $suggestions, string $field): string
    {
        $counts = [];

        foreach ($suggestions as $suggestion) {
            if (! method_exists($suggestion, $field)) {
                continue;
            }

            $value = $suggestion->{$field}();
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        ksort($counts);

        return implode(', ', array_map(
            static fn (string $value, int $count): string => sprintf('%s:%d', $value, $count),
            array_keys($counts),
            $counts
        ));
    }
}
