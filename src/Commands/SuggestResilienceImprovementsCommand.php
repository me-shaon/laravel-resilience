<?php

namespace MeShaon\LaravelResilience\Commands;

use Illuminate\Console\Command;
use MeShaon\LaravelResilience\Suggestions\SuggestionEngine;

class SuggestResilienceImprovementsCommand extends Command
{
    protected $signature = 'resilience:suggest
        {path? : Base path to scan}
        {--json : Output suggestions as JSON}
        {--category=* : Limit suggestions to one or more categories}';

    protected $description = 'Generate resilience improvement suggestions from discovery findings';

    public function handle(SuggestionEngine $engine): int
    {
        $basePath = $this->argument('path');
        $categories = array_values(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), (array) $this->option('category')),
            static fn (string $value): bool => $value !== ''
        ));

        $report = $engine->suggest(
            is_string($basePath) && trim($basePath) !== '' ? $basePath : null,
            $categories
        );

        if ($this->option('json')) {
            $this->line((string) json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Laravel Resilience suggestions');
        $this->line(sprintf('Scanned path: %s', $report->basePath()));
        $this->line(sprintf('Suggestions: %d', count($report->suggestions())));

        if ($report->suggestions() === []) {
            $this->info('No suggestions were generated from the current findings.');

            return self::SUCCESS;
        }

        foreach ($report->groupedSuggestions() as $category => $suggestions) {
            $this->newLine();
            $this->line(sprintf('%s:', $category));

            foreach ($suggestions as $suggestion) {
                $this->line(sprintf(
                    '- [%s|%s] %s [%s:%d]',
                    $suggestion->severity(),
                    $suggestion->assessment(),
                    $suggestion->recommendation(),
                    $suggestion->finding()->relativePath(),
                    $suggestion->finding()->line()
                ));

                if ($suggestion->evidence() !== []) {
                    $this->line(sprintf('  Evidence: %s', implode('; ', $suggestion->evidence())));
                }

                if ($suggestion->missingSignals() !== []) {
                    $this->line(sprintf('  Missing: %s', implode('; ', $suggestion->missingSignals())));
                }
            }
        }

        return self::SUCCESS;
    }
}
