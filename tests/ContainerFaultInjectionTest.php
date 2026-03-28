<?php

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use MeShaon\LaravelResilience\Faults\FaultScope;
use MeShaon\LaravelResilience\Faults\FaultTarget;
use MeShaon\LaravelResilience\Tests\Fixtures\Jobs\ExampleJob;
use MeShaon\LaravelResilience\Tests\Fixtures\Payments\FakePaymentGateway;
use MeShaon\LaravelResilience\Tests\Fixtures\Payments\PaymentGateway;
use MeShaon\LaravelResilience\Tests\Fixtures\Search\FakeSearchClient;
use MeShaon\LaravelResilience\Tests\Fixtures\Search\SearchClient;

it('injects timeout faults into container-bound interfaces', function () {
    app()->singleton(PaymentGateway::class, FakePaymentGateway::class);

    Resilience::for(PaymentGateway::class)->timeout();

    expect(fn () => app(PaymentGateway::class)->charge(500))
        ->toThrow(RuntimeException::class, 'Operation timed out.');
});

it('restores the original container binding after deactivation', function () {
    app()->singleton(PaymentGateway::class, FakePaymentGateway::class);

    $original = app(PaymentGateway::class);

    Resilience::for(PaymentGateway::class)->timeout();

    $instrumented = app(PaymentGateway::class);

    expect(get_class($original))->toBe(FakePaymentGateway::class)
        ->and(get_class($instrumented))->not->toBe(FakePaymentGateway::class);

    Resilience::deactivate(FaultTarget::container(PaymentGateway::class));

    $restored = app(PaymentGateway::class);

    expect(get_class($restored))->toBe(FakePaymentGateway::class)
        ->and($restored->charge(500))->toBe('charged:500');
});

it('supports bound concrete services and multiple active container targets', function () {
    app()->bind(SearchClient::class, FakeSearchClient::class);
    app()->singleton(PaymentGateway::class, FakePaymentGateway::class);

    Resilience::for(SearchClient::class)->exception(new RuntimeException('Search is down.'));
    Resilience::for(PaymentGateway::class)->timeout();

    expect(fn () => app(SearchClient::class)->search('laravel'))
        ->toThrow(RuntimeException::class, 'Search is down.')
        ->and(fn () => app(PaymentGateway::class)->charge(800))
        ->toThrow(RuntimeException::class, 'Operation timed out.');
});

it('cleans up test-scoped container faults without touching process-scoped rules', function () {
    app()->singleton(PaymentGateway::class, FakePaymentGateway::class);
    app()->bind(SearchClient::class, FakeSearchClient::class);

    Resilience::for(PaymentGateway::class)->timeout();
    Resilience::for(SearchClient::class)->process()->exception(new RuntimeException('Search is down.'));

    Resilience::deactivateScope(FaultScope::Test);

    expect(app(PaymentGateway::class)->charge(250))->toBe('charged:250')
        ->and(fn () => app(SearchClient::class)->search('resilience'))
        ->toThrow(RuntimeException::class, 'Search is down.');
});

it('injects timeout faults into the HTTP client facade', function () {
    $factory = new HttpFactory;
    $factory->fake(['*' => $factory->response(['ok' => true], 200)]);

    app()->instance(HttpFactory::class, $factory);

    Resilience::http()->timeout();

    expect(fn () => Http::get('https://example.com'))
        ->toThrow(RuntimeException::class, 'Operation timed out.');
});

it('injects exception faults into the mail facade', function () {
    Resilience::mail()->exception(new RuntimeException('Mail is down.'));

    expect(fn () => Mail::raw('hello', function ($message): void {
        $message->to('team@example.com')->subject('Resilience');
    }))->toThrow(RuntimeException::class, 'Mail is down.');
});

it('injects latency faults into the cache facade deterministically', function () {
    Resilience::cache()->latency(40);

    $startedAt = microtime(true);
    $result = Cache::put('resilience:cache:key', 'value', 60);
    $elapsed = microtime(true) - $startedAt;

    expect($result)->toBeTrue()
        ->and($elapsed)->toBeGreaterThanOrEqual(0.03)
        ->and(Cache::get('resilience:cache:key'))->toBe('value');
});

it('injects exception faults into queue dispatch', function () {
    Resilience::queue()->exception(new RuntimeException('Queue is down.'));

    expect(fn () => Queue::push(new ExampleJob))
        ->toThrow(RuntimeException::class, 'Queue is down.');
});

it('injects latency faults into filesystem operations', function () {
    $path = 'resilience/filesystem-example.txt';

    Resilience::storage()->latency(40);

    $startedAt = microtime(true);
    $result = Storage::put($path, 'hello');
    $elapsed = microtime(true) - $startedAt;

    expect($result)->toBeTrue()
        ->and($elapsed)->toBeGreaterThanOrEqual(0.03)
        ->and(Storage::get($path))->toBe('hello');

    Storage::delete($path);
});

it('targets a named cache store without affecting the default store', function () {
    config()->set('cache.stores.secondary', [
        'driver' => 'array',
        'serialize' => false,
    ]);

    Resilience::cache('secondary')->latency(40);

    $defaultStartedAt = microtime(true);
    $defaultResult = Cache::put('resilience:cache:default', 'default', 60);
    $defaultElapsed = microtime(true) - $defaultStartedAt;

    $secondaryStartedAt = microtime(true);
    $secondaryResult = Cache::store('secondary')->put('resilience:cache:secondary', 'secondary', 60);
    $secondaryElapsed = microtime(true) - $secondaryStartedAt;

    expect($defaultResult)->toBeTrue()
        ->and($defaultElapsed)->toBeLessThan(0.03)
        ->and($secondaryResult)->toBeTrue()
        ->and($secondaryElapsed)->toBeGreaterThanOrEqual(0.03)
        ->and(Cache::get('resilience:cache:default'))->toBe('default')
        ->and(Cache::store('secondary')->get('resilience:cache:secondary'))->toBe('secondary');
});

it('targets a named mailer without affecting the default mailer', function () {
    config()->set('mail.default', 'array');
    config()->set('mail.mailers.array', ['transport' => 'array']);
    config()->set('mail.mailers.secondary', ['transport' => 'array']);

    Resilience::mail('secondary')->exception(new RuntimeException('Secondary mailer is down.'));

    expect(fn () => Mail::raw('default mail', function ($message): void {
        $message->to('default@example.com')->subject('Default');
    }))->not->toThrow(RuntimeException::class)
        ->and(fn () => Mail::mailer('secondary')->raw('secondary mail', function ($message): void {
            $message->to('secondary@example.com')->subject('Secondary');
        }))->toThrow(RuntimeException::class, 'Secondary mailer is down.');
});

it('targets a named queue connection without affecting the default connection', function () {
    config()->set('queue.default', 'sync');
    config()->set('queue.connections.secondary', ['driver' => 'sync']);

    Resilience::queue('secondary')->exception(new RuntimeException('Secondary queue is down.'));

    expect(fn () => Queue::push(new ExampleJob))->not->toThrow(RuntimeException::class)
        ->and(fn () => Queue::connection('secondary')->push(new ExampleJob))
        ->toThrow(RuntimeException::class, 'Secondary queue is down.');
});

it('targets a named filesystem disk without affecting the default disk', function () {
    $secondaryRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'laravel-resilience-'.Str::uuid();

    config()->set('filesystems.default', 'local');
    config()->set('filesystems.disks.secondary', [
        'driver' => 'local',
        'root' => $secondaryRoot,
    ]);

    $defaultPath = 'resilience/default-disk.txt';
    $secondaryPath = 'resilience/secondary-disk.txt';

    Resilience::storage('secondary')->latency(40);

    $defaultStartedAt = microtime(true);
    $defaultResult = Storage::put($defaultPath, 'default');
    $defaultElapsed = microtime(true) - $defaultStartedAt;

    $secondaryStartedAt = microtime(true);
    $secondaryResult = Storage::disk('secondary')->put($secondaryPath, 'secondary');
    $secondaryElapsed = microtime(true) - $secondaryStartedAt;

    expect($defaultResult)->toBeTrue()
        ->and($defaultElapsed)->toBeLessThan(0.03)
        ->and($secondaryResult)->toBeTrue()
        ->and($secondaryElapsed)->toBeGreaterThanOrEqual(0.03)
        ->and(Storage::get($defaultPath))->toBe('default')
        ->and(Storage::disk('secondary')->get($secondaryPath))->toBe('secondary');

    Storage::delete($defaultPath);
    Storage::disk('secondary')->delete($secondaryPath);
});
