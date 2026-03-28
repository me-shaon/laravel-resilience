# Laravel Resilience

Laravel-native resilience testing and fault injection for application-level failure scenarios.

## Status

The package is in active development. The baseline package bootstrapping, rule-based fault model, container and Laravel-native fault injection, and the first assertion helpers are now in place.

## Compatibility

- PHP 8.1+
- Laravel 10, 11, 12, and 13

## Safety defaults

- `RESILIENCE_ENABLED=true` keeps the package available by default
- `resilience.blocked_environments` defaults to `['production']`
- removing `'production'` from `resilience.blocked_environments` explicitly allows production activation

## Planned capabilities

- deterministic fault injection for container-managed services and Laravel integrations
- resilience-oriented assertions for fallbacks, logs, events, jobs, and duplicate side effects
- discovery tooling that highlights resilience-sensitive touchpoints and suggests practical improvements

## Current model

The package now has a simple Phase 2 + Phase 4 model:

- a `FaultRule` describes the fault behavior we want
- the `Resilience` registry keeps track of active rules and their attempt counts
- the runtime wrapper applies those rules to container-managed services and Laravel-native targets, then restores the original binding when the rule is removed

### Container example

```php
use MeShaon\LaravelResilience\Facades\Resilience;
use App\Contracts\PaymentGateway;

Resilience::for(PaymentGateway::class)->timeout();

$gateway = app(PaymentGateway::class);

$gateway->charge(500); // throws RuntimeException('Operation timed out.')

Resilience::deactivateAll();
```

In this example, Laravel Resilience wraps the `PaymentGateway` container binding, intercepts the method call, applies the active timeout rule, and then lets you restore the original binding with `deactivateAll()` or `deactivate(...)`.

Laravel-specific helpers are also available for the first integration set:

```php
Resilience::http()->timeout();
Resilience::mail()->exception(new RuntimeException('Mail is down.'));
Resilience::cache()->latency(40);
Resilience::queue()->exception(new RuntimeException('Queue is down.'));
Resilience::storage()->latency(40);
```

Those helpers can also target named Laravel drivers when the application code uses them explicitly:

```php
Resilience::cache('redis')->latency(40);
Resilience::mail('ses')->exception(new RuntimeException('SES is down.'));
Resilience::queue('redis')->exception(new RuntimeException('Redis queue is down.'));
Resilience::storage('s3')->timeout();
```

That means `Cache::store('redis')`, `Mail::mailer('ses')`, `Queue::connection('redis')`, and `Storage::disk('s3')` can be faulted independently without affecting the default store, mailer, connection, or disk.

## Assertion helpers

Phase 5 adds a small assertion layer to keep resilience tests readable while still working with Laravel's normal testing tools:

```php
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use MeShaon\LaravelResilience\Facades\Resilience;

Log::spy();
Event::fake();
Bus::fake();

Resilience::assertFallbackUsed($responseSource, 'cache', 'response source fallback');
Resilience::assertLogWritten('warning', 'Cache fallback used.');
Resilience::assertEventDispatched(App\Events\CacheFallbackTriggered::class, times: 1);
Resilience::assertJobDispatched(App\Jobs\NotifyOps::class, times: 1);
Resilience::assertNoDuplicateSideEffects($writeCount, description: 'inventory write');
```

For degraded but still successful responses, combine your normal Laravel response checks with:

```php
Resilience::assertDegradedButSuccessful(
    $response,
    fn ($response) => $response->headers->get('X-Resilience-Degraded') === 'true'
);
```

## Installation

The package is not published yet. For local development, install dependencies with:

```bash
composer install
```

## Testing

```bash
composer test
composer analyse
composer format
```

## License

MIT. See [LICENSE.md](LICENSE.md).
