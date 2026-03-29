# Laravel Resilience

Laravel-native resilience testing and fault injection for application-level failure scenarios.

## Status

The package is in active development. The baseline package bootstrapping, rule-based fault model, container and Laravel-native fault injection, assertion helpers, and scenario runner are now in place.

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

The package currently works around a few simple ideas:

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

Laravel Resilience includes a small assertion layer to keep resilience tests readable while still working with Laravel's normal testing tools:

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

## Scenario runner

The scenario runner lets you define named resilience exercises and run them from Artisan.

A scenario is useful when you want to:

- activate one or more fault rules
- execute a real application workflow while those faults are active
- capture a structured result
- rerun the same resilience experiment by name later

Define scenarios in `config/resilience.php`:

```php
'scenarios' => [
    'search-fallback' => App\Resilience\SearchFallbackScenario::class,
],
```

Each scenario class should implement `MeShaon\LaravelResilience\Scenarios\ResilienceScenario`, return the fault rules it wants to activate, and provide a `run()` method.

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

When you run this scenario, Laravel Resilience will:

1. resolve the configured scenario by name
2. verify that fault activation is allowed in the current environment
3. activate the scenario's fault rules
4. execute the scenario's `run()` method
5. collect a result report and log entry
6. clean up the active faults afterward

Run a configured scenario with Artisan:

```bash
php artisan resilience:run search-fallback
```

That command is useful for repeatable resilience drills, local debugging, and shared team experiments. Instead of rebuilding fault setup manually, you can rerun the same named workflow whenever you need it.

For structured output:

```bash
php artisan resilience:run search-fallback --json
```

The JSON output is helpful if you want to inspect the result programmatically or feed it into later automation and reporting.

## Discovery scanner

Laravel Resilience also includes a discovery command for finding resilience-relevant code patterns before you have written many tests.

Use it when you want a quick review of code paths that are likely good candidates for:

- resilience tests
- fault injection coverage
- abstraction behind contracts or services
- follow-up architectural review

Run the scanner with:

```bash
php artisan resilience:discover
```

You can also scan a specific path:

```bash
php artisan resilience:discover app/Services
```

Or request structured output:

```bash
php artisan resilience:discover --json
```

The scanner currently looks for practical patterns such as:

- outbound HTTP calls
- mail send points
- queue dispatches
- storage writes
- cache usage
- direct construction of external-style clients
- constructors coupled to concrete client or gateway classes

Example output:

```text
Laravel Resilience discovery findings
Scanned path: /project/app
Files scanned: 18
Findings: 4

http:
- Outbound HTTP call through the Laravel HTTP client. [app/Services/BillingService.php:18]

queue:
- Queue or bus dispatch point. [app/Listeners/SendInvoiceListener.php:27]

client-construction:
- Direct construction of an external-style client. [app/Services/SearchService.php:14]
```

The scanner is intentionally heuristic-based. It does not try to prove that code is wrong. Instead, it highlights places that are often resilience-sensitive and worth reviewing.

So the output should be read as:

- "this code path probably deserves resilience attention"

not:

- "this code is definitely broken"

## Suggestion engine

The suggestion engine builds on top of discovery findings and turns them into practical next steps.
It does not just repeat "this area looks risky." It also tries to detect whether the code already has some resilience signals in place and then reports the gap.

Run it with:

```bash
php artisan resilience:suggest
```

Or scan a specific path:

```bash
php artisan resilience:suggest app/Services
```

For structured output:

```bash
php artisan resilience:suggest --json
```

Typical suggestions include:

- wrap this external client behind a service boundary
- introduce a contract for this concrete dependency
- extract HTTP logic from a controller or listener
- add a resilience scenario or fault-injection test for this flow
- review duplicate side effects or degraded behavior for this queue or storage path

Each suggestion includes:

- a severity level
- an assessment of `missing`, `partial`, or `covered`
- evidence the engine found in the same file or related tests
- missing signals that may deserve follow-up

Example output:

```text
http:
- [high|missing] Wrap this outbound HTTP dependency behind a service boundary and add a resilience scenario or timeout/fallback test around it. [app/Http/Controllers/CheckoutController.php:18]
  Missing: timeout handling not detected; local fallback or exception handling not detected; related tests or resilience scenarios not detected

- [high|covered] Existing safeguards were detected here. Review whether the current protections are enough before adding more resilience work. [app/Services/BillingService.php:22]
  Evidence: timeout handling detected in the same file; retry handling detected in the same file; local fallback or exception handling detected; related tests or resilience scenarios detected
```

The detection is still heuristic-based. It does not prove that code is safe or unsafe, and it will not understand every custom resilience pattern in an application. Its job is to give developers more helpful review guidance than a generic recommendation by showing what seems to be present already and what still appears to be missing.

## Installation

Install the package with Composer:

```bash
composer require me-shaon/laravel-resilience
```

Laravel package discovery will register the service provider and facade automatically.

If you want to customize the package safety defaults, publish the config file:

```bash
php artisan vendor:publish --tag="laravel-resilience-config"
```

## Testing

```bash
composer test
composer analyse
composer format
```

## License

MIT. See [LICENSE.md](LICENSE.md).
