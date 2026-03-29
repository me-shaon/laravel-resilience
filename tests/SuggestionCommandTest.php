<?php

use Illuminate\Support\Facades\Artisan;

it('prints readable grouped suggestions', function () {
    $exitCode = Artisan::call('resilience:suggest', ['path' => 'tests/Fixtures/Discovery']);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Laravel Resilience suggestions')
        ->and($output)->toContain('http:')
        ->and($output)->toContain('[high|missing]')
        ->and($output)->toContain('Evidence:')
        ->and($output)->toContain('Missing:');
});

it('supports json output for suggestions', function () {
    $exitCode = Artisan::call('resilience:suggest', [
        'path' => 'tests/Fixtures/Discovery',
        '--json' => true,
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('"suggestions"')
        ->and($output)->toContain('"severity"')
        ->and($output)->toContain('"assessment"')
        ->and($output)->toContain('"evidence"')
        ->and($output)->toContain('"missing_signals"')
        ->and($output)->toContain('ResilienceSampleController.php');
});

it('supports category filtering for suggestions', function () {
    $exitCode = Artisan::call('resilience:suggest', [
        'path' => 'tests/Fixtures/Discovery',
        '--category' => ['cache'],
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('cache:')
        ->and($output)->not->toContain('http:')
        ->and($output)->not->toContain('mail:');
});
