<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use MeShaon\LaravelResilience\Suggestions\SuggestionEngine;

beforeEach(function () {
    $suffix = uniqid('laravel-resilience-scaffold-', true);
    $this->scaffoldSandbox = sys_get_temp_dir().DIRECTORY_SEPARATOR.$suffix;
    $this->scaffoldOutput = $this->scaffoldSandbox.DIRECTORY_SEPARATOR.'generated';
    $this->scaffoldManifest = $this->scaffoldSandbox.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'resilience-scaffold.json';
    $this->coverageIgnoreRoot = getcwd().DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'GeneratedScaffoldIgnoreTmp'.DIRECTORY_SEPARATOR.$suffix;
});

afterEach(function () {
    app(Filesystem::class)->deleteDirectory($this->scaffoldSandbox);
    app(Filesystem::class)->deleteDirectory($this->coverageIgnoreRoot);
});

it('supports dry-run scaffold output without writing files', function () {
    $exitCode = Artisan::call('resilience:scaffold', [
        'path' => 'tests/Fixtures/Discovery',
        '--dry-run' => true,
        '--output' => $this->scaffoldOutput,
        '--manifest' => $this->scaffoldManifest,
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Laravel Resilience test scaffold')
        ->and($output)->toContain('Dry run complete')
        ->and(is_dir($this->scaffoldOutput))->toBeFalse()
        ->and(is_file($this->scaffoldManifest))->toBeFalse();
});

it('generates scaffold files and a manifest in create mode', function () {
    $exitCode = Artisan::call('resilience:scaffold', [
        'path' => 'tests/Fixtures/Discovery',
        '--output' => $this->scaffoldOutput,
        '--manifest' => $this->scaffoldManifest,
    ]);

    $output = Artisan::output();
    $files = app(Filesystem::class)->allFiles($this->scaffoldOutput);
    $expectedCount = count(app(SuggestionEngine::class)->suggest('tests/Fixtures/Discovery')->suggestions());
    $firstFile = $files[0]?->getPathname();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Generated')
        ->and($output)->toContain('Output directory:')
        ->and($files)->toHaveCount($expectedCount)
        ->and(is_file($this->scaffoldManifest))->toBeTrue()
        ->and($firstFile)->not->toBeNull()
        ->and(file_get_contents((string) $firstFile))->toContain('@laravel-resilience-hotspot:')
        ->and(file_get_contents((string) $firstFile))->toContain('Generated scaffold: replace placeholders');
});

it('does not create duplicate scaffold files when rerun in create mode', function () {
    Artisan::call('resilience:scaffold', [
        'path' => 'tests/Fixtures/Discovery',
        '--output' => $this->scaffoldOutput,
        '--manifest' => $this->scaffoldManifest,
    ]);

    $files = app(Filesystem::class)->allFiles($this->scaffoldOutput);
    $firstFile = $files[0]->getPathname();
    $firstHash = sha1_file($firstFile);

    $exitCode = Artisan::call('resilience:scaffold', [
        'path' => 'tests/Fixtures/Discovery',
        '--output' => $this->scaffoldOutput,
        '--manifest' => $this->scaffoldManifest,
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and(app(Filesystem::class)->allFiles($this->scaffoldOutput))->toHaveCount(count($files))
        ->and(sha1_file($firstFile))->toBe($firstHash)
        ->and($output)->toContain('[skipped] existing scaffold file left untouched');
});

it('does not overwrite customized scaffold files in update mode', function () {
    Artisan::call('resilience:scaffold', [
        'path' => 'tests/Fixtures/Discovery',
        '--output' => $this->scaffoldOutput,
        '--manifest' => $this->scaffoldManifest,
    ]);

    $firstFile = app(Filesystem::class)->allFiles($this->scaffoldOutput)[0]->getPathname();
    file_put_contents($firstFile, file_get_contents($firstFile)."\n// customized by user\n");

    $exitCode = Artisan::call('resilience:scaffold', [
        'path' => 'tests/Fixtures/Discovery',
        '--mode' => 'update',
        '--output' => $this->scaffoldOutput,
        '--manifest' => $this->scaffoldManifest,
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and(file_get_contents($firstFile))->toContain('// customized by user')
        ->and($output)->toContain('[customized] managed scaffold file was customized');
});

it('can overwrite customized scaffold files in force mode', function () {
    Artisan::call('resilience:scaffold', [
        'path' => 'tests/Fixtures/Discovery',
        '--output' => $this->scaffoldOutput,
        '--manifest' => $this->scaffoldManifest,
    ]);

    $firstFile = app(Filesystem::class)->allFiles($this->scaffoldOutput)[0]->getPathname();
    file_put_contents($firstFile, file_get_contents($firstFile)."\n// customized by user\n");

    $exitCode = Artisan::call('resilience:scaffold', [
        'path' => 'tests/Fixtures/Discovery',
        '--mode' => 'force',
        '--output' => $this->scaffoldOutput,
        '--manifest' => $this->scaffoldManifest,
    ]);

    expect($exitCode)->toBe(0)
        ->and(file_get_contents($firstFile))->not->toContain('// customized by user');
});

it('does not count skipped generated scaffold files as real coverage', function () {
    app(Filesystem::class)->ensureDirectoryExists($this->coverageIgnoreRoot);
    file_put_contents(
        $this->coverageIgnoreRoot.DIRECTORY_SEPARATOR.'GeneratedResilienceSampleControllerTest.php',
        <<<'PHP'
<?php

use MeShaon\LaravelResilience\Tests\Fixtures\Discovery\ResilienceSampleController;

it('generated placeholder', function () {
    expect(ResilienceSampleController::class)->toBeString();
})->skip('Generated scaffold: replace placeholders with the real application flow and assertions.');
PHP
    );

    $report = app(SuggestionEngine::class)->suggest('tests/Fixtures/Discovery', ['http']);
    $suggestion = $report->suggestions()[0] ?? null;

    expect($suggestion)->not->toBeNull()
        ->and($suggestion->assessment())->toBe('missing')
        ->and($suggestion->missingSignals())->toContain('related tests or resilience scenarios not detected');
});
