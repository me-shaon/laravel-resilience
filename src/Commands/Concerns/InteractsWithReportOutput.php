<?php

namespace MeShaon\LaravelResilience\Commands\Concerns;

use InvalidArgumentException;
use MeShaon\LaravelResilience\Reporting\ConsoleOutputMode;

trait InteractsWithReportOutput
{
    protected function resolveOutputMode(): ConsoleOutputMode
    {
        $compact = (bool) $this->option('compact');
        $view = strtolower(trim((string) ($this->option('view') ?? '')));

        if ($compact && $view !== '' && $view !== ConsoleOutputMode::Compact->value) {
            throw new InvalidArgumentException('The [--compact] shortcut cannot be combined with a non-compact [--view] mode.');
        }

        if ($compact) {
            return ConsoleOutputMode::Compact;
        }

        if ($view === '') {
            return ConsoleOutputMode::Default;
        }

        return match ($view) {
            ConsoleOutputMode::Compact->value => ConsoleOutputMode::Compact,
            ConsoleOutputMode::Default->value => ConsoleOutputMode::Default,
            ConsoleOutputMode::Verbose->value => ConsoleOutputMode::Verbose,
            default => throw new InvalidArgumentException('The [--view] option must be one of: compact, default, verbose.'),
        };
    }

    protected function htmlReportPathOption(): ?string
    {
        $value = $this->option('html');

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    protected function wantsHtmlReport(): bool
    {
        return $this->htmlReportPathOption() !== null || (bool) $this->option('preview');
    }

    protected function announceHtmlReport(string $path): void
    {
        $this->newLine();
        $this->info(sprintf('HTML report: %s', $path));

        if ((bool) $this->option('preview')) {
            $this->line(sprintf('Preview URL: %s', $this->toFileUrl($path)));
        }
    }

    private function toFileUrl(string $path): string
    {
        $normalizedPath = str_replace(DIRECTORY_SEPARATOR, '/', $path);
        $encodedPath = str_replace('%2F', '/', rawurlencode($normalizedPath));

        return str_starts_with($encodedPath, '/')
            ? 'file://'.$encodedPath
            : 'file:///'.$encodedPath;
    }
}
