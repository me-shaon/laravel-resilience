<?php

use MeShaon\LaravelResilience\Suggestions\ResilienceSuggestion;
use MeShaon\LaravelResilience\Suggestions\SuggestionEngine;

it('generates suggestions from discovery findings', function () {
    $report = app(SuggestionEngine::class)->suggest('tests/Fixtures/Discovery');

    $categories = array_map(
        fn ($suggestion) => $suggestion->category(),
        $report->suggestions()
    );

    expect($report->suggestions())->not->toBeEmpty()
        ->and($categories)->toContain('http')
        ->and($categories)->toContain('client-construction')
        ->and($categories)->toContain('concrete-dependency');
});

it('skips covered suggestions by default and groups repeated hotspots', function () {
    $report = app(SuggestionEngine::class)->suggest('tests/Fixtures/Discovery', ['http']);

    $suggestion = collect($report->suggestions())->first(
        fn (ResilienceSuggestion $suggestion): bool => str_ends_with($suggestion->finding()->relativePath(), 'ResilienceSampleController.php')
    );

    expect($report->suggestions())->toHaveCount(1)
        ->and($suggestion)->toBeInstanceOf(ResilienceSuggestion::class)
        ->and($suggestion->assessment())->toBe('missing')
        ->and($suggestion->action())->toBe('add timeout and fallback')
        ->and($suggestion->lineNumbers())->toBe([18]);
});

it('can include covered suggestions when requested', function () {
    $report = app(SuggestionEngine::class)->suggest('tests/Fixtures/Discovery', ['http'], true);
    $missingSuggestion = collect($report->suggestions())->first(
        fn (ResilienceSuggestion $suggestion): bool => str_ends_with($suggestion->finding()->relativePath(), 'ResilienceSampleController.php')
    );
    $coveredSuggestion = collect($report->suggestions())->first(
        fn (ResilienceSuggestion $suggestion): bool => str_ends_with($suggestion->finding()->relativePath(), 'GuardedBillingService.php')
    );

    expect($missingSuggestion)->toBeInstanceOf(ResilienceSuggestion::class)
        ->and($missingSuggestion->assessment())->toBe('missing')
        ->and($missingSuggestion->missingSignals())->toContain('timeout handling not detected')
        ->and($missingSuggestion->recommendation())->toContain('service boundary')
        ->and($coveredSuggestion)->toBeInstanceOf(ResilienceSuggestion::class)
        ->and($coveredSuggestion->assessment())->toBe('covered')
        ->and($coveredSuggestion->evidence())->toContain(
            'timeout handling detected in the same file',
            'retry handling detected in the same file',
            'local fallback or exception handling detected',
            'related tests or resilience scenarios detected'
        );
});
