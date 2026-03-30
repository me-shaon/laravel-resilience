<?php

namespace MeShaon\LaravelResilience\Reporting;

use Illuminate\Filesystem\Filesystem;
use MeShaon\LaravelResilience\Discovery\DiscoveryFinding;
use MeShaon\LaravelResilience\Discovery\DiscoveryReport;
use MeShaon\LaravelResilience\Suggestions\ResilienceSuggestion;
use MeShaon\LaravelResilience\Suggestions\SuggestionReport;

final class HtmlReportGenerator
{
    private readonly string $projectRoot;

    public function __construct(private readonly Filesystem $files)
    {
        $this->projectRoot = getcwd() ?: app()->basePath();
    }

    public function writeDiscoveryReport(
        DiscoveryReport $report,
        ConsoleOutputMode $mode,
        ?string $path = null
    ): string {
        $resolvedPath = $this->resolvePath($path, 'discover');

        $this->files->ensureDirectoryExists(dirname($resolvedPath));
        $this->files->put($resolvedPath, $this->renderDiscoveryReport($report, $mode));

        return $resolvedPath;
    }

    public function writeSuggestionReport(
        SuggestionReport $report,
        ConsoleOutputMode $mode,
        ?string $path = null
    ): string {
        $resolvedPath = $this->resolvePath($path, 'suggest');

        $this->files->ensureDirectoryExists(dirname($resolvedPath));
        $this->files->put($resolvedPath, $this->renderSuggestionReport($report, $mode));

        return $resolvedPath;
    }

    private function renderDiscoveryReport(DiscoveryReport $report, ConsoleOutputMode $mode): string
    {
        $groupedFindings = $report->groupedFindings();
        $summaryRows = array_map(
            static fn (string $category, array $findings): array => [
                $category,
                $findings[0]->summary(),
                (string) count($findings),
            ],
            array_keys($groupedFindings),
            $groupedFindings
        );

        $sections = [];

        foreach ($groupedFindings as $category => $findings) {
            $sections[] = $this->renderDiscoverySection($category, $findings, $mode);
        }

        return $this->renderPage(
            reportKind: 'discovery',
            title: 'Laravel Resilience discovery report',
            subtitle: 'Scan the codebase for resilience-relevant patterns.',
            stats: [
                ['label' => 'Scanned path', 'value' => $report->basePath()],
                ['label' => 'Files scanned', 'value' => (string) $report->filesScanned()],
                ['label' => 'Findings', 'value' => (string) count($report->findings())],
                ['label' => 'Mode', 'value' => ucfirst($mode->value)],
            ],
            summaryTitle: 'Category summary',
            summaryHeaders: ['Category', 'Summary', 'Findings'],
            summaryRows: $summaryRows,
            sectionsTitle: 'Findings by category',
            sectionsHtml: implode("\n", $sections)
        );
    }

    private function renderSuggestionReport(SuggestionReport $report, ConsoleOutputMode $mode): string
    {
        $groupedSuggestions = $report->groupedSuggestions();
        $summaryRows = array_map(
            fn (string $category, array $suggestions): array => [
                $category,
                (string) count($suggestions),
                $this->summarizeSuggestionField($suggestions, 'severity'),
                $this->summarizeSuggestionField($suggestions, 'assessment'),
            ],
            array_keys($groupedSuggestions),
            $groupedSuggestions
        );

        $sections = [];

        foreach ($groupedSuggestions as $category => $suggestions) {
            $cards = array_map(
                fn (ResilienceSuggestion $suggestion): string => $this->renderSuggestionCard($suggestion, $mode),
                $suggestions
            );

            $sections[] = $this->renderSection(
                $category,
                count($suggestions),
                implode("\n", $cards),
                $category
            );
        }

        return $this->renderPage(
            reportKind: 'suggestion',
            title: 'Laravel Resilience suggestion report',
            subtitle: 'Review practical next steps from discovery findings.',
            stats: [
                ['label' => 'Scanned path', 'value' => $report->basePath()],
                ['label' => 'Suggestions', 'value' => (string) count($report->suggestions())],
                ['label' => 'Categories', 'value' => (string) count($groupedSuggestions)],
                ['label' => 'Mode', 'value' => ucfirst($mode->value)],
            ],
            summaryTitle: 'Category summary',
            summaryHeaders: ['Category', 'Suggestions', 'Risk mix', 'Coverage mix'],
            summaryRows: $summaryRows,
            sectionsTitle: 'Suggestions by category',
            sectionsHtml: implode("\n", $sections)
        );
    }

    private function renderDiscoveryCard(DiscoveryFinding $finding, ConsoleOutputMode $mode): string
    {
        $parts = [
            '<article class="report-card report-card-discovery" data-entry data-category="'.$this->escape($finding->category()).'" data-location="'.$this->escape($this->location($finding->relativePath(), $finding->line())).'" data-summary="'.$this->escape($finding->summary()).'" data-excerpt="'.$this->escape($finding->excerpt()).'" data-search="'.$this->escape($this->searchIndex([
                $finding->category(),
                $finding->summary(),
                $finding->relativePath(),
                $finding->excerpt(),
            ])).'">',
            '<div class="card-shell">',
            '<div class="card-main">',
            '<h3 class="card-title card-title-discovery">'.$this->escape($this->discoveryFileLabel($finding->relativePath())).'</h3>',
            '<p class="card-subtitle">'.$this->escape($this->discoveryPathDirectory($finding->relativePath())).'</p>',
            '</div>',
            '<div class="card-side">',
            '<span class="location location-pill">Line '.$finding->line().'</span>',
            '</div>',
            '</div>',
        ];

        if ($mode === ConsoleOutputMode::Verbose) {
            $parts[] = '<div class="detail-block">';
            $parts[] = '<h4>Excerpt</h4>';
            $parts[] = '<pre>'.$this->escape($finding->excerpt()).'</pre>';
            $parts[] = '</div>';
        }

        $parts[] = '</article>';

        return implode("\n", $parts);
    }

    /**
     * @param  array<int, DiscoveryFinding>  $findings
     */
    private function renderDiscoverySection(string $category, array $findings, ConsoleOutputMode $mode): string
    {
        $rows = array_map(
            fn (DiscoveryFinding $finding): string => $this->renderDiscoveryCard($finding, $mode),
            $findings
        );

        return implode("\n", [
            '<section class="section-block" data-section data-category="'.$this->escape($category).'">',
            '<div class="section-header">',
            '<div>',
            '<h2>'.$this->escape($category).'</h2>',
            '<p class="section-description">'.$this->escape($findings[0]->summary()).'</p>',
            '</div>',
            '<span class="section-count">'.count($findings).' item'.(count($findings) === 1 ? '' : 's').'</span>',
            '</div>',
            '<div class="card-grid card-grid-discovery">',
            implode("\n", $rows),
            '</div>',
            '</section>',
        ]);
    }

    private function renderSuggestionCard(ResilienceSuggestion $suggestion, ConsoleOutputMode $mode): string
    {
        $finding = $suggestion->finding();
        $parts = [
            '<article class="report-card report-card-suggestion" data-entry data-category="'.$this->escape($suggestion->category()).'" data-location="'.$this->escape($this->location($finding->relativePath(), $finding->line())).'" data-severity="'.$this->escape($suggestion->severity()).'" data-assessment="'.$this->escape($suggestion->assessment()).'" data-recommendation="'.$this->escape($suggestion->recommendation()).'" data-evidence="'.$this->escape(implode(' || ', $suggestion->evidence())).'" data-missing="'.$this->escape(implode(' || ', $suggestion->missingSignals())).'" data-excerpt="'.$this->escape($finding->excerpt()).'" data-search="'.$this->escape($this->searchIndex([
                $suggestion->category(),
                $suggestion->severity(),
                $suggestion->assessment(),
                $suggestion->recommendation(),
                implode(' ', $suggestion->evidence()),
                implode(' ', $suggestion->missingSignals()),
                $finding->relativePath(),
                $finding->excerpt(),
            ])).'">',
            '<div class="card-shell">',
            '<div class="card-main">',
            '<div class="card-meta">',
            '<span class="badge badge-'.$this->escape($suggestion->severity()).'">'.$this->escape($suggestion->severity()).'</span>',
            '<span class="badge badge-outline">'.$this->escape($suggestion->assessment()).'</span>',
            '<span class="badge badge-subtle">'.$this->escape($suggestion->category()).'</span>',
            '</div>',
            '<h3 class="card-title">'.$this->escape($suggestion->recommendation()).'</h3>',
            '</div>',
            '<div class="card-side">',
            '<span class="location location-pill">'.$this->escape($this->location($finding->relativePath(), $finding->line())).'</span>',
            '</div>',
            '</div>',
        ];

        if ($mode !== ConsoleOutputMode::Compact && $suggestion->evidence() !== []) {
            $parts[] = $this->renderTokenList('Evidence', $suggestion->evidence(), 'token-positive');
        }

        if ($mode !== ConsoleOutputMode::Compact && $suggestion->missingSignals() !== []) {
            $parts[] = $this->renderTokenList('Missing signals', $suggestion->missingSignals(), 'token-negative');
        }

        if ($mode === ConsoleOutputMode::Verbose) {
            $parts[] = '<div class="detail-block">';
            $parts[] = '<h4>Excerpt</h4>';
            $parts[] = '<pre>'.$this->escape($finding->excerpt()).'</pre>';
            $parts[] = '</div>';
        }

        $parts[] = '</article>';

        return implode("\n", $parts);
    }

    /**
     * @param  array<int, string>  $items
     */
    private function renderTokenList(string $label, array $items, string $className): string
    {
        $tokens = implode('', array_map(
            fn (string $item): string => '<span class="token '.$this->escape($className).'">'.$this->escape($item).'</span>',
            $items
        ));

        return implode("\n", [
            '<div class="detail-block">',
            '<h4>'.$this->escape($label).'</h4>',
            '<div class="token-list">'.$tokens.'</div>',
            '</div>',
        ]);
    }

    private function renderSection(string $category, int $count, string $content, string $filterValue): string
    {
        return implode("\n", [
            '<section class="section-block" data-section data-category="'.$this->escape($filterValue).'">',
            '<div class="section-header">',
            '<h2>'.$this->escape($category).'</h2>',
            '<span class="section-count">'.$count.' item'.($count === 1 ? '' : 's').'</span>',
            '</div>',
            '<div class="card-grid">',
            $content,
            '</div>',
            '</section>',
        ]);
    }

    private function discoveryFileLabel(string $relativePath): string
    {
        $segments = explode('/', $relativePath);

        return (string) end($segments);
    }

    private function discoveryPathDirectory(string $relativePath): string
    {
        $directory = dirname($relativePath);

        return $directory === '.' ? $relativePath : $directory;
    }

    /**
     * @param  array<int, array{label: string, value: string}>  $stats
     * @param  array<int, string>  $summaryHeaders
     * @param  array<int, array<int, string>>  $summaryRows
     */
    private function renderPage(
        string $reportKind,
        string $title,
        string $subtitle,
        array $stats,
        string $summaryTitle,
        array $summaryHeaders,
        array $summaryRows,
        string $sectionsTitle,
        string $sectionsHtml
    ): string {
        $searchableCategories = [];

        foreach ($summaryRows as $row) {
            $searchableCategories[] = $row[0] ?? '';
        }

        $categoryFilters = implode('', array_map(
            fn (string $category): string => '<button type="button" class="filter-chip" data-filter="'.$this->escape($category).'">'.$this->escape($category).'</button>',
            $searchableCategories
        ));

        $statCards = implode('', array_map(
            fn (array $stat): string => implode("\n", [
                '<div class="stat-card">',
                '<span class="stat-label">'.$this->escape($stat['label']).'</span>',
                '<strong class="stat-value">'.$this->escape($stat['value']).'</strong>',
                '</div>',
            ]),
            $stats
        ));

        $summaryHead = implode('', array_map(
            fn (string $header): string => '<th>'.$this->escape($header).'</th>',
            $summaryHeaders
        ));

        $summaryBody = implode('', array_map(
            fn (array $row): string => '<tr>'.implode('', array_map(
                fn (string $column): string => '<td>'.$this->escape($column).'</td>',
                $row
            )).'</tr>',
            $summaryRows
        ));

        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>'.$this->escape($title).'</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #eef4fb;
            --panel: rgba(255, 255, 255, 0.92);
            --panel-strong: #ffffff;
            --text: #132238;
            --text-soft: #223554;
            --muted: #61748f;
            --border: rgba(19, 34, 56, 0.1);
            --accent: #0f766e;
            --accent-2: #2563eb;
            --accent-soft: rgba(15, 118, 110, 0.1);
            --danger: #b42318;
            --danger-soft: rgba(180, 35, 24, 0.1);
            --warning: #b54708;
            --warning-soft: rgba(181, 71, 8, 0.1);
            --shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
            --shadow-soft: 0 10px 24px rgba(15, 23, 42, 0.06);
            --radius: 22px;
            --radius-sm: 16px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "IBM Plex Sans", "Segoe UI", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 0% 0%, rgba(37, 99, 235, 0.18), transparent 28%),
                radial-gradient(circle at 100% 0%, rgba(15, 118, 110, 0.18), transparent 32%),
                linear-gradient(180deg, #f7fbff 0%, var(--bg) 45%, #f4f7fb 100%);
        }

        .page {
            width: min(1240px, calc(100% - 32px));
            margin: 28px auto 64px;
        }

        .hero,
        .panel {
            background: var(--panel);
            border: 1px solid var(--border);
            backdrop-filter: blur(12px);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .hero {
            position: relative;
            overflow: hidden;
            padding: 34px;
            margin-bottom: 24px;
            background:
                radial-gradient(circle at top right, rgba(148, 163, 184, 0.14), transparent 24%),
                linear-gradient(135deg, rgba(15, 23, 42, 0.96), rgba(15, 118, 110, 0.92) 58%, rgba(37, 99, 235, 0.88));
            color: #f8fbff;
        }

        .hero::after {
            content: "";
            position: absolute;
            inset: auto -80px -110px auto;
            width: 280px;
            height: 280px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            filter: blur(2px);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            position: relative;
            z-index: 1;
            background: rgba(255, 255, 255, 0.14);
            color: #f8fbff;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        h1,
        h2,
        h3,
        h4 {
            margin: 0;
            font-family: "Space Grotesk", "Segoe UI", sans-serif;
            letter-spacing: -0.02em;
        }

        h1 {
            position: relative;
            z-index: 1;
            margin-top: 16px;
            font-size: clamp(32px, 4vw, 52px);
        }

        .subtitle {
            position: relative;
            z-index: 1;
            max-width: 760px;
            margin-top: 12px;
            color: rgba(248, 251, 255, 0.78);
            font-size: 16px;
            line-height: 1.6;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-top: 28px;
            position: relative;
            z-index: 1;
        }

        .stat-card {
            padding: 18px 20px 16px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(10px);
        }

        .stat-label {
            display: block;
            color: rgba(248, 251, 255, 0.68);
            font-size: 12px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .stat-value {
            display: block;
            color: #ffffff;
            font-size: 17px;
            line-height: 1.45;
            word-break: break-word;
        }

        .panel {
            padding: 24px;
            margin-bottom: 18px;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            margin-bottom: 18px;
        }

        .panel-header p {
            margin: 8px 0 0;
            color: var(--muted);
        }

        .summary-panel {
            overflow: hidden;
        }

        .results-panel {
            position: relative;
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 18px;
            position: sticky;
            top: 14px;
            z-index: 2;
            padding: 14px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.88);
            border: 1px solid rgba(19, 34, 56, 0.08);
            backdrop-filter: blur(14px);
            box-shadow: var(--shadow-soft);
        }

        .toolbar input {
            flex: 1 1 280px;
            min-width: 0;
            padding: 13px 15px;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: var(--panel-strong);
            font: inherit;
            color: var(--text);
        }

        .toolbar-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .toolbar-button {
            border: 1px solid transparent;
            background: linear-gradient(135deg, #0f766e, #0b5ed7);
            color: #ffffff;
            border-radius: 14px;
            padding: 11px 15px;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
            box-shadow: 0 10px 20px rgba(15, 118, 110, 0.14);
        }

        .toolbar-button:hover {
            transform: translateY(-1px);
        }

        .toolbar-button.secondary {
            background: var(--panel-strong);
            color: var(--text);
            border-color: var(--border);
            box-shadow: none;
        }

        .toolbar-meta {
            margin: 0 0 18px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 16px;
        }

        .filter-chip {
            border: 1px solid var(--border);
            background: var(--panel-strong);
            color: var(--text);
            border-radius: 999px;
            padding: 10px 14px;
            font: inherit;
            cursor: pointer;
            transition: 0.18s ease;
        }

        .filter-chip:hover,
        .filter-chip.is-active {
            border-color: rgba(15, 118, 110, 0.28);
            background: var(--accent-soft);
            color: var(--accent);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            overflow: hidden;
        }

        th,
        td {
            text-align: left;
            padding: 13px 12px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }

        th {
            color: var(--muted);
            font-size: 12px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        tbody tr:nth-child(even) {
            background: rgba(97, 116, 143, 0.03);
        }

        .section-block + .section-block {
            margin-top: 18px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 12px;
        }

        .section-count {
            color: var(--muted);
            font-size: 14px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(97, 116, 143, 0.08);
        }

        .section-description {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.55;
        }

        .card-grid {
            display: grid;
            gap: 10px;
        }

        .card-grid-discovery {
            gap: 8px;
        }

        .report-card {
            padding: 16px 18px;
            border-radius: 18px;
            background: var(--panel-strong);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-soft);
            transition: border-color 0.18s ease, transform 0.18s ease, box-shadow 0.18s ease;
        }

        .report-card:hover {
            border-color: rgba(37, 99, 235, 0.18);
            transform: translateY(-1px);
            box-shadow: 0 16px 28px rgba(15, 23, 42, 0.08);
        }

        .report-card-discovery {
            padding: 10px 14px;
            border-radius: 16px;
        }

        .card-shell {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 14px;
            align-items: start;
        }

        .card-main {
            min-width: 0;
        }

        .card-side {
            display: flex;
            align-items: flex-start;
        }

        .card-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin-bottom: 10px;
        }

        .badge,
        .token {
            display: inline-flex;
            align-items: center;
            padding: 7px 11px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            line-height: 1;
        }

        .badge-neutral {
            background: rgba(37, 99, 235, 0.08);
            color: #1d4ed8;
        }

        .badge-subtle {
            background: rgba(19, 34, 56, 0.06);
            color: var(--text);
        }

        .badge-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--muted);
        }

        .badge-high {
            background: var(--danger-soft);
            color: var(--danger);
        }

        .badge-medium {
            background: var(--warning-soft);
            color: var(--warning);
        }

        .badge-low {
            background: var(--accent-soft);
            color: var(--accent);
        }

        .location {
            color: var(--muted);
            font-size: 13px;
            word-break: break-word;
        }

        .location-pill {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(97, 116, 143, 0.08);
            border: 1px solid rgba(97, 116, 143, 0.12);
            white-space: nowrap;
        }

        .card-title {
            font-size: 17px;
            line-height: 1.45;
            color: var(--text-soft);
        }

        .card-title-compact {
            font-size: 14px;
            line-height: 1.45;
            font-weight: 700;
            color: var(--text);
        }

        .card-title-discovery {
            font-size: 14px;
            line-height: 1.35;
            font-weight: 700;
            color: var(--text);
        }

        .card-subtitle {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.45;
        }

        .detail-block {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid rgba(97, 116, 143, 0.12);
        }

        .detail-block h4 {
            margin-bottom: 8px;
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .token-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .token-positive {
            background: var(--accent-soft);
            color: var(--accent);
        }

        .token-negative {
            background: var(--danger-soft);
            color: var(--danger);
        }

        pre {
            margin: 0;
            padding: 14px 15px;
            border-radius: 16px;
            background: #0f172a;
            color: #dbeafe;
            font-family: "IBM Plex Mono", "SFMono-Regular", monospace;
            font-size: 12px;
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-word;
            overflow-x: auto;
        }

        [hidden] {
            display: none !important;
        }

        @media (max-width: 720px) {
            .page {
                width: min(100% - 20px, 1200px);
                margin-top: 20px;
            }

            .hero,
            .panel {
                padding: 18px;
            }

            .toolbar {
                position: static;
                padding: 12px;
            }

            th,
            td {
                padding-inline: 8px;
            }

            .card-shell {
                grid-template-columns: 1fr;
            }

            .card-side {
                justify-content: flex-start;
            }

            .location-pill {
                white-space: normal;
            }
        }
    </style>
</head>
<body>
    <main class="page" data-report-kind="'.$this->escape($reportKind).'" data-report-title="'.$this->escape($title).'" data-report-mode="'.$this->escape($stats[count($stats) - 1]['value'] ?? '').'" data-base-path="'.$this->escape($stats[0]['value'] ?? '').'">
        <section class="hero">
            <span class="eyebrow">Laravel Resilience</span>
            <h1>'.$this->escape($title).'</h1>
            <p class="subtitle">'.$this->escape($subtitle).'</p>
            <div class="stats-grid">'.$statCards.'</div>
        </section>

        <section class="panel summary-panel">
            <div class="panel-header">
                <div>
                    <h2>'.$this->escape($summaryTitle).'</h2>
                    <p>Use this snapshot to spot the busiest resilience categories before drilling into individual findings.</p>
                </div>
            </div>
            <table>
                <thead>
                    <tr>'.$summaryHead.'</tr>
                </thead>
                <tbody>'.$summaryBody.'</tbody>
            </table>
        </section>

        <section class="panel results-panel">
            <div class="panel-header">
                <div>
                    <h2>'.$this->escape($sectionsTitle).'</h2>
                    <p>Search across paths, categories, recommendations, and extracted signals.</p>
                </div>
            </div>

            <div class="toolbar">
                <input type="search" id="report-search" placeholder="Search this report">
                <div class="toolbar-actions">
                    <button type="button" class="toolbar-button" id="copy-visible-prompt">Copy visible AI prompt</button>
                    <button type="button" class="toolbar-button secondary" id="copy-full-prompt">Copy full AI prompt</button>
                </div>
            </div>

            <p class="toolbar-meta" id="copy-status">Filter the report if you want a narrower prompt, then copy the visible items for an AI agent review.</p>

            <div class="filter-row">
                <button type="button" class="filter-chip is-active" data-filter="all">All categories</button>
                '.$categoryFilters.'
            </div>

            <div id="report-sections">'.$sectionsHtml.'</div>
        </section>
    </main>

    <script>
        (function () {
            const search = document.getElementById("report-search");
            const chips = Array.from(document.querySelectorAll("[data-filter]"));
            const sections = Array.from(document.querySelectorAll("[data-section]"));
            const copyVisibleButton = document.getElementById("copy-visible-prompt");
            const copyFullButton = document.getElementById("copy-full-prompt");
            const copyStatus = document.getElementById("copy-status");
            const page = document.querySelector(".page");
            let activeFilter = "all";

            const applyFilters = () => {
                const term = (search.value || "").trim().toLowerCase();

                sections.forEach((section) => {
                    const entries = Array.from(section.querySelectorAll("[data-entry]"));
                    let sectionVisible = false;

                    entries.forEach((entry) => {
                        const category = (entry.dataset.category || "").toLowerCase();
                        const text = (entry.dataset.search || "").toLowerCase();
                        const matchesFilter = activeFilter === "all" || category === activeFilter;
                        const matchesSearch = term === "" || text.includes(term);
                        const visible = matchesFilter && matchesSearch;

                        entry.hidden = ! visible;
                        sectionVisible = sectionVisible || visible;
                    });

                    section.hidden = ! sectionVisible;
                });
            };

            const decodeList = (value) => {
                if (! value) {
                    return [];
                }

                return value
                    .split(" || ")
                    .map((item) => item.trim())
                    .filter(Boolean);
            };

            const buildPrompt = (entries, scopeLabel) => {
                const reportKind = page.dataset.reportKind || "report";
                const reportMode = page.dataset.reportMode || "Default";
                const basePath = page.dataset.basePath || "";
                const title = page.dataset.reportTitle || "Laravel Resilience report";
                const visibleCategories = Array.from(new Set(entries.map((entry) => entry.dataset.category || "").filter(Boolean)));
                const promptHeader = reportKind === "suggestion"
                    ? [
                        "You are reviewing Laravel Resilience suggestions for a Laravel codebase.",
                        "",
                        "Please verify each suggestion against the code before acting on it.",
                        "Prioritize the work by severity and assessment, identify false positives if any, and propose or implement the most valuable fixes, fallbacks, abstractions, and tests.",
                        "Return:",
                        "- prioritized issues",
                        "- concrete code or test changes",
                        "- any suggestions that should be downgraded or skipped",
                        "- assumptions or missing context",
                    ]
                    : [
                        "You are reviewing Laravel Resilience discovery findings for a Laravel codebase.",
                        "",
                        "Please verify whether each finding is real, group related issues where helpful, and propose or implement the most valuable resilience tests, abstractions, fallbacks, and follow-up checks.",
                        "Call out false positives if any.",
                        "Return:",
                        "- prioritized findings",
                        "- recommended fixes or resilience tests",
                        "- any findings that can be safely ignored",
                        "- assumptions or missing context",
                    ];

                const promptEntries = entries.map((entry, index) => {
                    const lines = [
                        (index + 1) + ". Category: " + (entry.dataset.category || ""),
                    ];

                    if (reportKind === "suggestion") {
                        lines.push("   Severity: " + (entry.dataset.severity || ""));
                        lines.push("   Assessment: " + (entry.dataset.assessment || ""));
                        lines.push("   Recommendation: " + (entry.dataset.recommendation || ""));

                        const evidence = decodeList(entry.dataset.evidence || "");
                        const missing = decodeList(entry.dataset.missing || "");

                        if (evidence.length > 0) {
                            lines.push("   Evidence: " + evidence.join("; "));
                        }

                        if (missing.length > 0) {
                            lines.push("   Missing signals: " + missing.join("; "));
                        }
                    } else {
                        lines.push("   Summary: " + (entry.dataset.summary || ""));
                    }

                    lines.push("   Location: " + (entry.dataset.location || ""));

                    if (entry.dataset.excerpt) {
                        lines.push("   Excerpt: " + entry.dataset.excerpt);
                    }

                    return lines.join("\n");
                });

                return [
                    ...promptHeader,
                    "",
                    "Context:",
                    "- Report title: " + title,
                    "- Scope: " + scopeLabel,
                    "- Base path: " + basePath,
                    "- Report mode: " + reportMode,
                    "- Categories: " + (visibleCategories.length > 0 ? visibleCategories.join(", ") : "none"),
                    "- Item count: " + entries.length,
                    "",
                    reportKind === "suggestion" ? "Suggestions:" : "Discovery findings:",
                    ...promptEntries,
                ].join("\n");
            };

            const copyText = async (text) => {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    await navigator.clipboard.writeText(text);
                    return;
                }

                const textarea = document.createElement("textarea");
                textarea.value = text;
                textarea.setAttribute("readonly", "readonly");
                textarea.style.position = "absolute";
                textarea.style.left = "-9999px";
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand("copy");
                document.body.removeChild(textarea);
            };

            const handleCopy = async (visibleOnly) => {
                const entries = Array.from(document.querySelectorAll("[data-entry]")).filter((entry) => ! visibleOnly || ! entry.hidden);

                if (entries.length === 0) {
                    copyStatus.textContent = "Nothing is currently visible to copy. Adjust the search or category filter first.";
                    return;
                }

                const scopeLabel = visibleOnly
                    ? "Visible report items after the current search/filter state"
                    : "Entire HTML report";

                try {
                    await copyText(buildPrompt(entries, scopeLabel));
                    copyStatus.textContent = visibleOnly
                        ? "Copied an AI-ready prompt for the visible report items."
                        : "Copied an AI-ready prompt for the full report.";
                } catch (error) {
                    copyStatus.textContent = "Copy failed in this browser. You can still inspect the report and copy manually.";
                }
            };

            chips.forEach((chip) => {
                chip.addEventListener("click", () => {
                    activeFilter = (chip.dataset.filter || "all").toLowerCase();

                    chips.forEach((item) => item.classList.toggle("is-active", item === chip));
                    applyFilters();
                });
            });

            search.addEventListener("input", applyFilters);
            copyVisibleButton.addEventListener("click", () => { void handleCopy(true); });
            copyFullButton.addEventListener("click", () => { void handleCopy(false); });
            applyFilters();
        }());
    </script>
</body>
</html>';
    }

    private function resolvePath(?string $path, string $prefix): string
    {
        if (is_string($path) && trim($path) !== '') {
            $normalizedPath = trim($path);

            if ($this->isAbsolutePath($normalizedPath)) {
                return $normalizedPath;
            }

            return $this->projectRoot.DIRECTORY_SEPARATOR.$normalizedPath;
        }

        return $this->projectRoot
            .DIRECTORY_SEPARATOR.'build'
            .DIRECTORY_SEPARATOR.'resilience-reports'
            .DIRECTORY_SEPARATOR.sprintf('%s-%s.html', $prefix, date('Ymd-His'));
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    /**
     * @param  array<int, string>  $parts
     */
    private function searchIndex(array $parts): string
    {
        return mb_strtolower(implode(' ', $parts));
    }

    private function location(string $relativePath, int $line): string
    {
        return sprintf('%s:%d', $relativePath, $line);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param  array<int, ResilienceSuggestion>  $suggestions
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
