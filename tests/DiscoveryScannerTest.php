<?php

use MeShaon\LaravelResilience\Discovery\DiscoveryScanner;

it('identifies resilience-relevant patterns in fixture code', function () {
    $report = app(DiscoveryScanner::class)->scan('tests/Fixtures/Discovery');

    $categories = array_map(
        fn ($finding) => $finding->category(),
        $report->findings()
    );

    expect($report->filesScanned())->toBe(4)
        ->and($categories)->toContain('http')
        ->and($categories)->toContain('mail')
        ->and($categories)->toContain('queue')
        ->and($categories)->toContain('storage')
        ->and($categories)->toContain('cache')
        ->and($categories)->toContain('client-construction')
        ->and($categories)->toContain('concrete-dependency');
});

it('can filter findings by category', function () {
    $report = app(DiscoveryScanner::class)->scan('tests/Fixtures/Discovery', ['http']);

    expect($report->findings())->toHaveCount(2)
        ->and($report->findings()[0]->category())->toBe('http')
        ->and($report->findings()[1]->category())->toBe('http');
});

it('ignores unrelated files reasonably well', function () {
    $report = app(DiscoveryScanner::class)->scan('tests/Fixtures/Discovery');

    $utilityFindings = array_filter(
        $report->findings(),
        fn ($finding): bool => $finding->relativePath() === 'tests/Fixtures/Discovery/UtilityClass.php'
    );

    expect($utilityFindings)->toBe([]);
});
