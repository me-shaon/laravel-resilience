<?php

namespace MeShaon\LaravelResilience\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use MeShaon\LaravelResilience\Scaffolding\ResilienceTestScaffolder;
use MeShaon\LaravelResilience\Scaffolding\ScaffoldMode;

class ScaffoldResilienceTestsCommand extends Command
{
    protected $signature = 'resilience:scaffold
        {path? : Base path to scan for actionable suggestions}
        {--category=* : Limit scaffolding to one or more categories}
        {--include-covered : Include suggestions that already look covered}
        {--dry-run : Show what would be scaffolded without writing files}
        {--mode=create : Scaffold mode: create, update, or force}
        {--format=pest : Scaffold format. Only pest is currently supported}
        {--output= : Output directory for generated tests}
        {--manifest= : Manifest path used to track generated scaffold files}';

    protected $description = 'Generate draft resilience tests from actionable suggestion hotspots';

    public function handle(ResilienceTestScaffolder $scaffolder): int
    {
        try {
            $mode = ScaffoldMode::from(strtolower((string) $this->option('mode')));
        } catch (\ValueError) {
            $this->error('The [--mode] option must be one of: create, update, force.');

            return self::INVALID;
        }

        $categories = array_values(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), (array) $this->option('category')),
            static fn (string $value): bool => $value !== ''
        ));

        try {
            $report = $scaffolder->scaffold(
                is_string($this->argument('path')) && trim((string) $this->argument('path')) !== '' ? (string) $this->argument('path') : null,
                $categories,
                (bool) $this->option('include-covered'),
                $mode,
                strtolower((string) $this->option('format')),
                $this->stringOption('output'),
                $this->stringOption('manifest'),
                (bool) $this->option('dry-run'),
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $this->info('Laravel Resilience test scaffold');
        $this->line(sprintf('Mode: %s', $report->mode()->value));
        $this->line(sprintf('Format: %s', $report->format()));
        $this->line(sprintf('Output directory: %s', $report->outputDirectory()));
        $this->line(sprintf('Manifest: %s', $report->manifestPath()));

        if ($report->items() === []) {
            $this->info('No actionable suggestion hotspots were available for scaffolding.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['Generated', 'Updated', 'Skipped', 'Customized', 'Total'],
            [[
                $report->countByStatus('generated'),
                $report->countByStatus('updated'),
                $report->countByStatus('skipped'),
                $report->countByStatus('customized'),
                $report->total(),
            ]]
        );

        $this->table(
            ['Status', 'Action', 'Hotspot', 'Output'],
            array_map(
                static fn ($item): array => [
                    $item->status(),
                    $item->suggestion()->action(),
                    sprintf(
                        '%s:%s',
                        $item->suggestion()->finding()->relativePath(),
                        implode(',', $item->suggestion()->lineNumbers())
                    ),
                    $item->outputPath(),
                ],
                $report->items()
            )
        );

        if ($report->dryRun()) {
            $this->info('Dry run complete. No scaffold files were written.');

            return self::SUCCESS;
        }

        $this->line('Notes:');

        foreach ($report->items() as $item) {
            $this->line(sprintf('- [%s] %s', $item->status(), $item->reason()));
        }

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
