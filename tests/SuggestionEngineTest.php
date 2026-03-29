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

it('assesses which safeguards are missing and which are already present', function () {
    $report = app(SuggestionEngine::class)->suggest('tests/Fixtures/Discovery', ['http']);
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
