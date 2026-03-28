<?php

use Illuminate\Support\Facades\Artisan;

it('prints readable grouped discovery findings', function () {
    $exitCode = Artisan::call('resilience:discover', ['path' => 'tests/Fixtures/Discovery']);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Laravel Resilience discovery findings')
        ->and($output)->toContain('http:')
        ->and($output)->toContain('mail:')
        ->and($output)->toContain('tests/Fixtures/Discovery/ResilienceSampleController.php');
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
        ->and($output)->toContain('http:')
        ->and($output)->not->toContain('mail:')
        ->and($output)->not->toContain('queue:');
});
