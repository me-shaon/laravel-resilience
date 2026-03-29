<?php

namespace MeShaon\LaravelResilience\Commands;

use Illuminate\Console\Command;
use MeShaon\LaravelResilience\Scenarios\ScenarioRunner;
use Throwable;

class RunResilienceScenarioCommand extends Command
{
    protected $signature = 'resilience:run
        {scenario : Configured scenario name}
        {--json : Output the run report as JSON}
        {--dry-run : Inspect the scenario without activating faults or executing it}
        {--confirm-non-local : Confirm execution outside configured safe environments}';

    protected $description = 'Run a configured Laravel Resilience scenario';

    public function handle(ScenarioRunner $runner): int
    {
        $name = (string) $this->argument('scenario');
        $dryRun = (bool) $this->option('dry-run');
        $confirmedNonLocal = (bool) $this->option('confirm-non-local');

        try {
            $report = $runner->run($name, $confirmedNonLocal, $dryRun);
        } catch (Throwable $exception) {
            $this->line(sprintf('Unable to run scenario [%s]: %s', $name, $exception->getMessage()));

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $report->successful() || $report->dryRun() ? self::SUCCESS : self::FAILURE;
        }

        $this->info(sprintf('Scenario [%s]', $report->name()));
        $this->line(sprintf('Description: %s', $report->description()));

        $this->table(
            ['Status', 'Environment', 'Duration (ms)'],
            [[
                $report->status(),
                $report->environment(),
                (string) $report->durationInMilliseconds(),
            ]]
        );

        if ($report->activatedFaults() !== []) {
            $this->line('Activated faults:');

            foreach ($report->activatedFaults() as $faultName) {
                $this->line(sprintf('- %s', $faultName));
            }
        }

        if ($report->result() !== null) {
            $this->line('Result:');
            $this->line((string) json_encode($report->result(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        if ($report->dryRun()) {
            $this->info(sprintf('Scenario [%s] dry run completed. No faults were activated.', $report->name()));

            return self::SUCCESS;
        }

        if (! $report->successful()) {
            $this->line(sprintf(
                'Scenario [%s] failed: %s',
                $report->name(),
                $report->exceptionMessage() ?? 'Unknown scenario failure.'
            ));

            return self::FAILURE;
        }

        $this->info(sprintf('Scenario [%s] completed successfully.', $report->name()));

        return self::SUCCESS;
    }
}
