# Laravel Resilience

Laravel Resilience helps you verify how a Laravel app behaves when a real dependency becomes slow, times out, or fails.

Instead of replacing that dependency with a mock, it injects faults into the actual container-managed service or Laravel integration your code normally uses. That makes it useful for checking fallback behavior, degraded responses, retries, logs, jobs, and duplicate-side-effect protection in a more realistic way.

## Why it is useful

Use this package when you want to:

- test failure handling against real Laravel wiring, not only mocks
- inject timeouts, exceptions, or latency into real dependency seams
- verify fallback behavior, degraded responses, logs, events, or jobs
- scan an existing codebase for areas that probably need resilience work
- scaffold draft resilience tests from actionable hotspots

In short:

- use mocks for fast unit-level feedback
- use Laravel Resilience for confidence that the real failure path works

## Compatibility

- PHP 8.1+
- Laravel 10, 11, 12, and 13

## Installation

```bash
composer require me-shaon/laravel-resilience
```

Laravel package discovery registers the service provider and facade automatically.

If you want to customize the defaults:

```bash
php artisan vendor:publish --tag="laravel-resilience-config"
```

## Quick start

The easiest way to adopt the package is:

1. discover likely resilience-sensitive code
2. turn findings into suggestions
3. scaffold draft tests for the highest-value gaps
4. refine those drafts into real application-specific resilience tests

```bash
php artisan resilience:discover
php artisan resilience:suggest
php artisan resilience:scaffold
```

What each command does:

- `resilience:discover` finds likely resilience-sensitive code such as HTTP calls, queue dispatches, cache usage, storage writes, and direct client construction
- `resilience:suggest` turns findings into practical next actions such as adding a fallback test, extracting a dependency seam, or tightening duplicate-side-effect protection
- `resilience:scaffold` generates draft Pest tests for actionable hotspots and is safe to rerun

If you want the fuller walkthrough, CLI examples, report options, and longer explanations, read [Usage Guide](guides/usage-guide.md).

The HTML reports are useful when the CLI output gets too large:

```bash
php artisan resilience:discover --html --preview
php artisan resilience:suggest --html --preview
```

## A quick example

Application code:

```php
use App\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class CheckoutService
{
    public function __construct(
        private PaymentGateway $paymentGateway
    ) {}

    public function charge(int $amount): array
    {
        try {
            $this->paymentGateway->charge($amount);

            return ['status' => 'paid'];
        } catch (RuntimeException $exception) {
            Log::warning('Payment gateway timeout.', [
                'amount' => $amount,
            ]);

            return ['status' => 'retry'];
        }
    }
}
```

Resilience test:

```php
use App\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Log;
use MeShaon\LaravelResilience\Facades\Resilience;

Log::spy();

Resilience::for(PaymentGateway::class)->timeout();

$result = app(CheckoutService::class)->charge(500);

Resilience::assertFallbackUsed(
    $result['status'],
    'retry',
    'payment status after gateway timeout'
);

Resilience::assertLogWritten('warning', 'Payment gateway timeout.');

Resilience::deactivateAll();
```

What this proves:

- the real application flow runs
- the real dependency seam is faulted
- your fallback path is exercised under failure
- the outcome is asserted in a readable way

## Best fit

Laravel Resilience works best when your app already has clear dependency seams.

Best-supported patterns:

- dependencies resolved through Laravel container bindings
- contracts or interfaces for critical external services
- outbound integrations wrapped in service classes
- isolated side effects in jobs, actions, or dedicated services
- normal Laravel HTTP, mail, cache, queue, and storage entry points

Weakly supported patterns:

- direct `new SomeSdkClient()` calls inside controllers or jobs
- static third-party SDK calls with no container seam
- business logic and IO tightly coupled in the same class

Those areas can still be discovered and suggested, but they usually need some refactoring before fault injection becomes truly effective.

## Main capabilities

### Fault injection

You can fault container-managed services directly:

```php
use App\Contracts\PaymentGateway;
use MeShaon\LaravelResilience\Facades\Resilience;

Resilience::for(PaymentGateway::class)->timeout();
Resilience::deactivateAll();
```

Supported fluent fault types for direct runtime injection:

- `timeout()`
- `exception(Throwable $exception)`
- `latency(int $milliseconds)`

Laravel-aware helpers are also available:

```php
Resilience::http()->timeout();
Resilience::mail()->exception(new \RuntimeException('Mail is down.'));
Resilience::cache()->latency(40);
Resilience::queue()->exception(new \RuntimeException('Queue is down.'));
Resilience::storage()->latency(40);
```

Named targets are supported too:

```php
Resilience::cache('redis')->latency(40);
Resilience::mail('ses')->exception(new \RuntimeException('SES is down.'));
Resilience::queue('redis')->exception(new \RuntimeException('Redis queue is down.'));
Resilience::storage('s3')->timeout();
```

### Assertions

The package includes readable assertions for common resilience outcomes:

- `assertFallbackUsed()`
- `assertLogWritten()`
- `assertEventDispatched()`
- `assertJobDispatched()`
- `assertNoDuplicateSideEffects()`
- `assertDegradedButSuccessful()`

### Scenario runner

Scenarios let you define a named resilience drill and run it from Artisan:

```bash
php artisan resilience:run payment-fallback
php artisan resilience:run payment-fallback --json
php artisan resilience:run payment-fallback --dry-run
```

### Discovery, suggestions, and scaffolding

These commands help teams adopt resilience testing incrementally:

- `resilience:discover` finds likely resilience-sensitive code
- `resilience:suggest` prioritizes actionable follow-up work
- `resilience:scaffold` generates draft Pest tests under `tests/Resilience/Generated`

Scaffold behavior:

- writes a manifest to `build/resilience-scaffold.json`
- skips existing generated files in normal create mode
- avoids overwriting customized generated files in update mode
- only overwrites generated files when you explicitly use `--mode=force`

## Safety defaults

Laravel Resilience is intentionally conservative:

- runtime activation is blocked in `production` by default
- scenario execution is only treated as safe by default in `local` and `testing`
- non-local scenario execution requires both `RESILIENCE_ALLOW_NON_LOCAL_SCENARIOS=true` and `--confirm-non-local`
- `--dry-run` lets you inspect a scenario without activating faults

## Command reference

```bash
php artisan resilience:run {scenario} [--json] [--dry-run] [--confirm-non-local]
php artisan resilience:discover {path?} [--json] [--category=*] [--compact] [--view=default|compact|verbose] [--html[=path]] [--preview]
php artisan resilience:suggest {path?} [--json] [--category=*] [--include-covered] [--compact] [--view=default|compact|verbose] [--html[=path]] [--preview]
php artisan resilience:scaffold {path?} [--category=*] [--include-covered] [--dry-run] [--mode=create|update|force] [--format=pest] [--output=path] [--manifest=path]
```

## Further reading

If you want the deeper internals and longer examples, use the guides instead of growing the README:

- [Usage Guide](guides/usage-guide.md)
- [How Laravel Resilience Works](guides/how-laravel-resilience-works.md)
- [Example: Well-Structured App](guides/example-well-structured-app.md)
- [Example: Messy Legacy App](guides/example-messy-legacy-app.md)

## Development

```bash
composer test
composer analyse
composer format
```

## License

MIT. See [LICENSE.md](LICENSE.md).
