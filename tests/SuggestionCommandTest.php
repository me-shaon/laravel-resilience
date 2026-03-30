<?php

use Illuminate\Support\Facades\Artisan;

it('prints readable grouped suggestions', function () {
    $exitCode = Artisan::call('resilience:suggest', ['path' => 'tests/Fixtures/Discovery']);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Laravel Resilience suggestions')
        ->and($output)->toContain('Category')
        ->and($output)->toContain('Severity')
        ->and($output)->toContain('Assessment')
        ->and($output)->toContain('Action')
        ->and($output)->toContain('Recommendation')
        ->and($output)->toContain('http (')
        ->and($output)->toContain('Next focus:')
        ->and($output)->toContain('timeout handling not detected')
        ->and($output)->not->toContain('GuardedBillingService.php');
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
        ->and($output)->toContain('"action"')
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
        ->and($output)->toContain('cache (')
        ->and($output)->not->toContain('http (')
        ->and($output)->not->toContain('mail (');
});

it('supports compact suggestion output', function () {
    $exitCode = Artisan::call('resilience:suggest', [
        'path' => 'tests/Fixtures/Discovery',
        '--compact' => true,
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Category')
        ->and($output)->toContain('Severity')
        ->and($output)->toContain('Assessment')
        ->and($output)->toContain('Action')
        ->and($output)->not->toContain('Signals:');
});

it('supports verbose suggestion output', function () {
    $exitCode = Artisan::call('resilience:suggest', [
        'path' => 'tests/Fixtures/Discovery',
        '--view' => 'verbose',
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Signals:')
        ->and($output)->toContain('Excerpt:')
        ->and($output)->toContain("Http::post('https://example.com/api/orders', ['id' => 1]);");
});

it('can include covered suggestions when requested', function () {
    $exitCode = Artisan::call('resilience:suggest', [
        'path' => 'tests/Fixtures/Discovery',
        '--include-covered' => true,
        '--category' => ['http'],
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('GuardedBillingService.php');
});

it('writes an html suggestion report and prints a preview url', function () {
    $reportPath = sys_get_temp_dir().'/laravel-resilience-suggestion-report.html';

    if (is_file($reportPath)) {
        unlink($reportPath);
    }

    $exitCode = Artisan::call('resilience:suggest', [
        'path' => 'tests/Fixtures/Discovery',
        '--html' => $reportPath,
        '--preview' => true,
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('HTML report:')
        ->and($output)->toContain('Preview URL: file://')
        ->and(is_file($reportPath))->toBeTrue()
        ->and(file_get_contents($reportPath))->toContain('Laravel Resilience suggestion report')
        ->and(file_get_contents($reportPath))->toContain('Search this report')
        ->and(file_get_contents($reportPath))->toContain('Copy full AI prompt')
        ->and(file_get_contents($reportPath))->toContain('data-recommendation=')
        ->and(file_get_contents($reportPath))->toContain('data-action=');

    unlink($reportPath);
});

it('rejects compact and verbose suggestion modes together', function () {
    $exitCode = Artisan::call('resilience:suggest', [
        'path' => 'tests/Fixtures/Discovery',
        '--compact' => true,
        '--view' => 'verbose',
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(2)
        ->and($output)->toContain('cannot be combined');
});
