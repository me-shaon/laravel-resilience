<?php

use Illuminate\Support\Facades\Artisan;

it('prints readable grouped discovery findings', function () {
    $exitCode = Artisan::call('resilience:discover', ['path' => 'tests/Fixtures/Discovery']);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Laravel Resilience discovery findings')
        ->and($output)->toContain('Category')
        ->and($output)->toContain('Summary')
        ->and($output)->toContain('Location')
        ->and($output)->toContain('http (')
        ->and($output)->toContain('mail (')
        ->and($output)->toContain('tests/Fixtures/Discovery/ResilienceSampleController.php:');
});

it('supports json output for discovery findings', function () {
    $exitCode = Artisan::call('resilience:discover', [
        'path' => 'tests/Fixtures/Discovery',
        '--json' => true,
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('"base_path"')
        ->and($output)->toContain('"findings"')
        ->and($output)->toContain('ResilienceSampleController.php');
});

it('supports category filters for discovery output', function () {
    $exitCode = Artisan::call('resilience:discover', [
        'path' => 'tests/Fixtures/Discovery',
        '--category' => ['http'],
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('http (')
        ->and($output)->not->toContain('mail (')
        ->and($output)->not->toContain('queue (');
});

it('supports compact discovery output', function () {
    $exitCode = Artisan::call('resilience:discover', [
        'path' => 'tests/Fixtures/Discovery',
        '--compact' => true,
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Category')
        ->and($output)->toContain('Location')
        ->and($output)->not->toContain('Excerpts:');
});

it('supports verbose discovery output', function () {
    $exitCode = Artisan::call('resilience:discover', [
        'path' => 'tests/Fixtures/Discovery',
        '--view' => 'verbose',
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Excerpts:')
        ->and($output)->toContain("Http::post('https://example.com/api/orders', ['id' => 1]);");
});

it('writes an html discovery report and prints a preview url', function () {
    $reportPath = sys_get_temp_dir().'/laravel-resilience-discovery-report.html';

    if (is_file($reportPath)) {
        unlink($reportPath);
    }

    $exitCode = Artisan::call('resilience:discover', [
        'path' => 'tests/Fixtures/Discovery',
        '--html' => $reportPath,
        '--preview' => true,
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('HTML report:')
        ->and($output)->toContain('Preview URL: file://')
        ->and(is_file($reportPath))->toBeTrue()
        ->and(file_get_contents($reportPath))->toContain('Laravel Resilience discovery report')
        ->and(file_get_contents($reportPath))->toContain('Search this report')
        ->and(file_get_contents($reportPath))->toContain('Copy visible AI prompt')
        ->and(file_get_contents($reportPath))->toContain('data-summary=')
        ->and(file_get_contents($reportPath))->toContain('section-description');

    unlink($reportPath);
});

it('rejects compact and verbose discovery modes together', function () {
    $exitCode = Artisan::call('resilience:discover', [
        'path' => 'tests/Fixtures/Discovery',
        '--compact' => true,
        '--view' => 'verbose',
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(2)
        ->and($output)->toContain('cannot be combined');
});
