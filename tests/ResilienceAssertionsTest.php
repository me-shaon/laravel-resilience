<?php

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use MeShaon\LaravelResilience\Tests\Fixtures\Events\ExampleFallbackEvent;
use MeShaon\LaravelResilience\Tests\Fixtures\Jobs\ExampleJob;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;

it('asserts fallback values with a readable failure message', function () {
    Resilience::assertFallbackUsed('cached-response', 'cached-response', 'cache fallback');

    expect(fn () => Resilience::assertFallbackUsed('live-response', 'cached-response', 'cache fallback'))
        ->toThrow(ExpectationFailedException::class, 'cache fallback');
});

it('asserts log entries after the logger is spied', function () {
    Log::spy();

    Log::warning('Cache fallback used.', ['source' => 'redis']);

    Resilience::assertLogWritten('warning', 'Cache fallback used.');
    Resilience::assertLogWritten(
        'warning',
        fn (mixed $message, mixed $context = []): bool => $message === 'Cache fallback used.'
            && is_array($context)
            && ($context['source'] ?? null) === 'redis'
    );
});

it('fails clearly when log assertions run without a spy', function () {
    expect(fn () => Resilience::assertLogWritten('warning', 'Cache fallback used.'))
        ->toThrow(AssertionFailedError::class, 'Log assertions require calling Log::spy()');
});

it('asserts dispatched events and queued jobs using laravel fakes', function () {
    Event::fake();
    Bus::fake();

    event(new ExampleFallbackEvent('cache'));
    dispatch(new ExampleJob);

    Resilience::assertEventDispatched(
        ExampleFallbackEvent::class,
        fn (ExampleFallbackEvent $event): bool => $event->source === 'cache',
        1
    );

    Resilience::assertJobDispatched(ExampleJob::class, times: 1);
});

it('asserts degraded but successful responses', function () {
    $response = TestResponse::fromBaseResponse(
        response()->json(
            ['status' => 'degraded'],
            200,
            ['X-Resilience-Degraded' => 'true']
        )
    );

    Resilience::assertDegradedButSuccessful(
        $response,
        fn (TestResponse $response): bool => $response->headers->get('X-Resilience-Degraded') === 'true'
    );

    expect(fn () => Resilience::assertDegradedButSuccessful($response, fn (): bool => false))
        ->toThrow(ExpectationFailedException::class, 'degraded response signal');
});

it('asserts side effects do not happen more than expected', function () {
    Resilience::assertNoDuplicateSideEffects(1, description: 'queue dispatch');

    expect(fn () => Resilience::assertNoDuplicateSideEffects(2, description: 'queue dispatch'))
        ->toThrow(ExpectationFailedException::class, 'queue dispatch');
});
