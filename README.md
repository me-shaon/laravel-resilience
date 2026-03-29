# Laravel Resilience

Laravel Resilience is a Laravel package for simulating failures in the parts of your application you depend on, such as HTTP APIs, mail, queues, cache, storage, and container-managed services. It helps you verify fallbacks, degraded responses, retries, and duplicate-side-effect protections before real outages or slowdowns affect production.

## Why use this package?

Use Laravel Resilience when you want to:

- force a timeout, exception, or slowdown in a dependency
- test how your application behaves when a service is unavailable
- make resilience tests easier to read with dedicated assertions
- run repeatable resilience drills through Artisan commands
- scan your codebase for resilience-sensitive areas and get follow-up suggestions

## Status

The package is in active development.

Available today:

- fault injection for container-managed services
- Laravel-aware helpers for HTTP, mail, cache, queue, and storage/filesystem
- readable assertions for fallback-oriented tests
- a scenario runner for repeatable resilience exercises
- discovery and suggestion commands for finding resilience-sensitive code paths

Current limitation:

- container and Laravel integration proxies currently support `timeout`, `exception`, and `latency` rules

## Compatibility

- PHP 8.1+
- Laravel 10, 11, 12, and 13

## Installation

Install the package with Composer:

```bash
composer require me-shaon/laravel-resilience
```

Laravel package discovery will register the service provider and facade automatically.

If you want to customize the defaults, publish the config file:

```bash
php artisan vendor:publish --tag="laravel-resilience-config"
```

## Configuration and safety defaults

Laravel Resilience is intentionally conservative.

- `resilience.enabled` defaults to `true`
- `resilience.blocked_environments` defaults to `['production']`
- removing `'production'` from `resilience.blocked_environments` explicitly allows activation in production
- scenario execution is treated as safe by default only in `local` and `testing`
- running scenarios in another environment requires both `RESILIENCE_ALLOW_NON_LOCAL_SCENARIOS=true` and `--confirm-non-local`
- `--dry-run` lets you inspect a scenario without activating faults or running the scenario body

Default config:

```php
return [
    'enabled' => (bool) env('RESILIENCE_ENABLED', true),

    'blocked_environments' => ['production'],

    'scenarios' => [
        // 'search-fallback' => \App\Resilience\SearchFallbackScenario::class,
    ],

    'scenario_runner' => [
        'safe_environments' => ['local', 'testing'],
        'allow_non_local' => (bool) env('RESILIENCE_ALLOW_NON_LOCAL_SCENARIOS', false),
    ],

    'discovery' => [
        'paths' => ['app'],
    ],
];
```

You can inspect the current activation state at runtime:

```php
use MeShaon\LaravelResilience\Facades\Resilience;

$status = Resilience::activationStatus();
```

## Quick start

The most common workflow is:

1. activate a fault for a dependency
2. run the code path you want to exercise
3. assert that the application handled the failure well
4. clean up the active fault

Example:

```php
use App\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Log;
use MeShaon\LaravelResilience\Facades\Resilience;

Log::spy();

Resilience::for(PaymentGateway::class)->timeout();

$result = rescue(
    fn () => app(PaymentGateway::class)->charge(500),
    function (): string {
        Log::warning('Payment gateway timeout.');

        return 'queued-for-retry';
    },
    report: false,
);

Resilience::assertFallbackUsed($result, 'queued-for-retry', 'payment fallback');
Resilience::assertLogWritten('warning', fn (mixed $message): bool => $message === 'Payment gateway timeout.');

Resilience::deactivateAll();
```

## Core concepts

Laravel Resilience revolves around a few simple ideas:

- `FaultRule`: describes the failure behavior you want to inject
- `FaultTarget`: identifies what the rule applies to
- `FaultScope`: controls whether a rule is test-scoped or process-scoped
- `Resilience`: the facade used to activate, inspect, and deactivate rules

For most usage, you will work through the facade builder:

```php
Resilience::for(App\Contracts\PaymentGateway::class)->timeout();
Resilience::mail()->exception(new \RuntimeException('Mail is down.'));
Resilience::cache()->latency(50);
```

## Fault injection

### Container-managed services

If a service is resolved through Laravel's container, you can fault it directly:

```php
use App\Contracts\PaymentGateway;
use MeShaon\LaravelResilience\Facades\Resilience;

Resilience::for(PaymentGateway::class)->timeout();

app(PaymentGateway::class)->charge(500); // throws RuntimeException('Operation timed out.')

Resilience::deactivateAll();
```

Available fluent fault types for container injection:

- `timeout()`
- `exception(Throwable $exception)`
- `latency(int $milliseconds)`

Laravel Resilience wraps the target binding, intercepts method calls, applies the active rule, and restores the original binding when you deactivate the target or call `deactivateAll()`.

### Laravel integrations

The package also includes Laravel-aware shortcuts for common framework integrations:

```php
use MeShaon\LaravelResilience\Facades\Resilience;

Resilience::http()->timeout();
Resilience::mail()->exception(new \RuntimeException('Mail is down.'));
Resilience::cache()->latency(40);
Resilience::queue()->exception(new \RuntimeException('Queue is down.'));
Resilience::storage()->latency(40);
```

These helpers work with:

- Laravel HTTP client
- mail
- cache
- queue
- storage/filesystem

`Resilience::storage()` is an alias of `Resilience::filesystem()`.

### Named drivers and connections

You can target a specific store, mailer, queue connection, or disk without affecting the default one:

```php
use MeShaon\LaravelResilience\Facades\Resilience;

Resilience::cache('redis')->latency(40);
Resilience::mail('ses')->exception(new \RuntimeException('SES is down.'));
Resilience::queue('redis')->exception(new \RuntimeException('Redis queue is down.'));
Resilience::storage('s3')->timeout();
```

That allows you to fault only:

- `Cache::store('redis')`
- `Mail::mailer('ses')`
- `Queue::connection('redis')`
- `Storage::disk('s3')`

### Scope control

Faults created through the fluent builder are test-scoped by default:

```php
Resilience::for(App\Contracts\SearchClient::class)->timeout();
```

If you want a process-scoped rule instead, use `process()`:

```php
Resilience::for(App\Contracts\SearchClient::class)
    ->process()
    ->exception(new \RuntimeException('Search is down.'));
```

You can clear only one scope when needed:

```php
use MeShaon\LaravelResilience\Faults\FaultScope;

Resilience::deactivateScope(FaultScope::Test);
```

## Assertion helpers

Laravel Resilience includes assertions for common resilience outcomes, so your tests can describe behavior instead of repeating low-level checks.

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

For degraded but still successful HTTP responses:

```php
Resilience::assertDegradedButSuccessful(
    $response,
    fn ($response) => $response->headers->get('X-Resilience-Degraded') === 'true'
);
```

Notes:

- call `Log::spy()` before using `assertLogWritten()`
- use Laravel fakes such as `Event::fake()` and `Bus::fake()` before event and job assertions

## Scenario runner

Scenarios let you define a named resilience exercise and rerun it from Artisan.

This is useful when you want to:

- activate one or more faults
- execute a real application workflow
- capture a structured result
- rerun the same drill later by name

Register scenarios in `config/resilience.php`:

```php
'scenarios' => [
    'payment-fallback' => App\Resilience\PaymentFallbackScenario::class,
],
```

Each scenario class must implement `MeShaon\LaravelResilience\Scenarios\ResilienceScenario`.

Example:

```php
<?php

namespace App\Resilience;

use App\Contracts\PaymentGateway;
use MeShaon\LaravelResilience\Faults\FaultRule;
use MeShaon\LaravelResilience\Faults\FaultTarget;
use MeShaon\LaravelResilience\Scenarios\ResilienceScenario;
use RuntimeException;

final class PaymentFallbackScenario implements ResilienceScenario
{
    public function description(): string
    {
        return 'Forces the payment gateway to timeout and verifies the fallback path.';
    }

    public function faultRules(): array
    {
        return [
            FaultRule::timeout('payment-timeout', FaultTarget::container(PaymentGateway::class)),
        ];
    }

    public function run(): array
    {
        try {
            app(PaymentGateway::class)->charge(500);

            return ['fallback_used' => false];
        } catch (RuntimeException $exception) {
            return [
                'fallback_used' => true,
                'message' => $exception->getMessage(),
            ];
        }
    }
}
```

Run it with:

```bash
php artisan resilience:run payment-fallback
```

Useful options:

```bash
php artisan resilience:run payment-fallback --json
php artisan resilience:run payment-fallback --dry-run
RESILIENCE_ALLOW_NON_LOCAL_SCENARIOS=true php artisan resilience:run payment-fallback --confirm-non-local
```

When a scenario runs, Laravel Resilience:

1. resolves the configured scenario
2. verifies the current environment is allowed
3. activates the scenario's fault rules
4. executes the scenario body
5. returns a structured report
6. logs the run and cleans up active faults

## Discovery scanner

The discovery scanner helps you find code paths that are likely to need resilience attention.

Run it with:

```bash
php artisan resilience:discover
php artisan resilience:discover app/Services
php artisan resilience:discover --json
php artisan resilience:discover --category=http --category=queue
```

Current finding categories:

- `http`
- `mail`
- `queue`
- `storage`
- `cache`
- `client-construction`
- `concrete-dependency`

What it is good for:

- spotting outbound integration points
- finding direct construction of clients and gateways
- identifying areas that probably deserve resilience tests or architectural review

The scanner is heuristic-based. It highlights likely resilience-sensitive code, not proven defects.

## Suggestion engine

The suggestion engine builds on discovery findings and turns them into practical follow-up recommendations.

Run it with:

```bash
php artisan resilience:suggest
php artisan resilience:suggest app/Services
php artisan resilience:suggest --json
php artisan resilience:suggest --category=cache
```

Suggestions include:

- a severity level
- an assessment of `missing`, `partial`, or `covered`
- evidence already detected in the codebase
- missing signals that may need more work

This makes the command more helpful than a generic warning because it tries to show both what is already present and what still appears to be missing.

## Advanced fault rules

If you need more control than the fluent builder provides, you can work directly with `FaultRule` and `FaultTarget`.

Available rule factories include:

- `FaultRule::exception(...)`
- `FaultRule::timeout(...)`
- `FaultRule::latency(...)`
- `FaultRule::failFirst(...)`
- `FaultRule::recoverAfter(...)`
- `FaultRule::percentage(...)`
- `FaultRule::percentageOnAttempts(...)`

Example:

```php
use MeShaon\LaravelResilience\Facades\Resilience;
use MeShaon\LaravelResilience\Faults\FaultRule;
use MeShaon\LaravelResilience\Faults\FaultTarget;

$target = FaultTarget::integration('payment-gateway');

Resilience::activate(
    FaultRule::failFirst('payment-fails-first-two-attempts', $target, 2)
);

Resilience::shouldActivate($target, 1); // true
Resilience::shouldActivate($target, 2); // true
Resilience::shouldActivate($target, 3); // false
```

This is especially useful for custom integrations or application-specific resilience logic that is not being proxied through the container helpers.

Important:

- direct container and Laravel integration injection currently supports only `timeout`, `exception`, and `latency`
- the richer rule model is still useful for custom targets and deterministic resilience logic in your own code

## Command reference

```bash
php artisan resilience:run {scenario} [--json] [--dry-run] [--confirm-non-local]
php artisan resilience:discover {path?} [--json] [--category=*]
php artisan resilience:suggest {path?} [--json] [--category=*]
```

## Development

```bash
composer test
composer analyse
composer format
```

## License

MIT. See [LICENSE.md](LICENSE.md).
