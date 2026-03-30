# Laravel Resilience

Laravel Resilience helps you test how your Laravel application behaves when a real dependency becomes slow, times out, or goes down. Instead of replacing that dependency with a mock, it injects faults into the actual container-managed service or Laravel integration your code normally uses, so you can verify fallbacks, degraded responses, retries, logs, jobs, and duplicate-side-effect protection in a more realistic way.

## Why use this package?

Use Laravel Resilience when you want to:

- test what really happens when a dependency fails, not just whether a mock was called
- run your normal application flow while a real timeout, exception, or slowdown is injected
- verify your fallback behavior, degraded responses, logs, events, jobs, and retry paths
- repeat the same resilience exercise later through named scenarios and Artisan commands
- scan your codebase for places that probably deserve resilience coverage

In short: use mocks when you want fast unit-level feedback about your own code. Use Laravel Resilience when you want confidence that the real Laravel wiring and failure-handling path still behave correctly when a dependency breaks.

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

## An easy way to start

You do not need to begin by writing resilience tests from scratch.

The easiest onboarding path is:

1. run the discovery command to find resilience-sensitive parts of your app
2. run the suggestion command to see where resilience coverage is missing
3. scaffold draft resilience tests for the highest-value hotspots
4. refine the generated drafts into real application-specific resilience tests

Start with:

```bash
php artisan resilience:discover
php artisan resilience:suggest
php artisan resilience:scaffold
```

What these commands help you see:

- `resilience:discover` shows code paths that look resilience-sensitive, such as HTTP calls, queue dispatches, cache usage, storage writes, or direct construction of external clients
- `resilience:suggest` turns those findings into practical next steps, such as adding a fallback test, extracting a dependency behind a service boundary, or reviewing duplicate-side-effect protection
- `resilience:scaffold` turns actionable suggestion hotspots into draft Pest tests under `tests/Resilience/Generated`

Example:

```text
$ php artisan resilience:discover

Laravel Resilience discovery findings
Scanned path: /project/app
Files scanned: 18
Findings: 4

+---------------------+----------+
| Category            | Findings |
+---------------------+----------+
| http                | 1        |
| queue               | 1        |
+---------------------+----------+

http (1)
+----------------------------------------------+------------------------------------+
| Summary                                      | Location                           |
+----------------------------------------------+------------------------------------+
| Outbound HTTP call through the Laravel HTTP  | app/Services/BillingService.php:18 |
| client.                                      |                                    |
+----------------------------------------------+------------------------------------+

queue (1)
+----------------------------------+-------------------------------------------+
| Summary                          | Location                                  |
+----------------------------------+-------------------------------------------+
| Queue or bus dispatch point.     | app/Listeners/SendInvoiceListener.php:27  |
+----------------------------------+-------------------------------------------+

$ php artisan resilience:suggest

Laravel Resilience suggestions
Scanned path: /project/app
Suggestions: 2

+---------------------+-------------+-----------+--------------+----------------------+
| Category            | Suggestions | Risk mix  | Coverage mix | Action mix           |
+---------------------+-------------+-----------+--------------+----------------------+
| http                | 1           | high:1    | missing:1    | add timeout and      |
|                     |             |           |              | fallback:1           |
| queue               | 1           | medium:1  | partial:1    | add resilience       |
|                     |             |           |              | test:1               |
+---------------------+-------------+-----------+--------------+----------------------+

http (1)
+----------+------------+--------------------------+------------------------------------+--------------------------------------------------------------+
| Severity | Assessment | Action                   | Hotspot                            | Recommendation                                               |
+----------+------------+--------------------------+------------------------------------+--------------------------------------------------------------+
| high     | missing    | add timeout and fallback | app/Services/BillingService.php:18 | Wrap this outbound HTTP dependency behind a service boundary |
|          |            |                          |                                    | and add a resilience scenario or timeout/fallback test       |
|          |            |                          |                                    | around it. This is often a good place to extract network     |
|          |            |                          |                                    | logic out of controllers and listeners.                      |
+----------+------------+--------------------------+------------------------------------+--------------------------------------------------------------+
Next focus:
- app/Services/BillingService.php:18: timeout handling not detected; local fallback or exception handling not detected; related tests or resilience scenarios not detected
```

This makes the package easier to adopt because it can first help you answer:

- which parts of my app are most likely to fail in real life?
- where should I add resilience tests first?
- which flows already look partially protected?

Then, once you know where the risky paths are, you can write targeted resilience tests for those flows.

If you want Laravel Resilience to generate the first draft for you, use the scaffold command after `resilience:suggest`:

```bash
php artisan resilience:scaffold
php artisan resilience:scaffold --dry-run
php artisan resilience:scaffold --mode=update
```

The scaffold command is designed to be rerun safely:

- it writes draft tests into `tests/Resilience/Generated`
- it tracks generated hotspots in `build/resilience-scaffold.json`
- it skips existing scaffold files in normal create mode
- it does not overwrite customized scaffold files in update mode
- it only overwrites generated files when you explicitly use `--mode=force`

For larger projects, you do not have to stay with the default terminal layout.

Output options:

- Default output: grouped tables in the terminal. Use this when you want a readable overview without extra detail.
- `--compact`: flattens the report into a denser table. Use this when your scan is large and you want to review more rows at once in the terminal.
- `--view=verbose`: keeps the grouped tables but also includes excerpts and richer signal detail. Use this when you are actively inspecting why a finding or suggestion appeared.
- `resilience:suggest` hides `covered` suggestions by default so the report stays focused on work that is still likely worth doing.
- `--include-covered`: brings already-covered suggestions back when you want a broader audit instead of an action-first view.
- `--html`: writes a standalone HTML report under `build/resilience-reports` by default. Use this when the CLI output is too long to comfortably review.
- `--html=path/to/report.html`: writes the HTML report to a specific location you choose.
- `--preview`: prints a browser-ready `file://` URL for the generated HTML report so you can open it immediately.
- `resilience:scaffold --dry-run`: previews which draft tests would be generated without writing files.
- `resilience:scaffold --mode=create|update|force`: controls whether generated scaffold files are only created, refreshed, or forcibly replaced.

HTML report workflow:

- the HTML report adds search, category filters, and a more spacious layout for large scans
- it includes copy buttons for full-report or filtered AI-ready prompts
- this makes it easy to narrow the report in the browser and paste the current result into an AI agent for review, validation, or follow-up fixes

Examples:

```bash
php artisan resilience:discover --compact
php artisan resilience:suggest --view=verbose
php artisan resilience:suggest --include-covered
php artisan resilience:scaffold --dry-run
php artisan resilience:discover --html
php artisan resilience:suggest --html=build/resilience-reports/suggest.html --preview
```

## A quick example

The clearest way to understand the package is to see both:

- the real application code
- the resilience test that injects the failure

Imagine your checkout flow uses a payment gateway. If the gateway times out, your application should log the problem and mark the payment for retry instead of crashing.

Application code:

```php
use App\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class CheckoutService
{
    public function __construct(
        private PaymentGateway $paymentService
    ) {}

    public function charge(int $amount): array
    {
        try {
            $this->paymentService->charge($amount);

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

// Assert that the application switched to the retry fallback path.
Resilience::assertFallbackUsed(
    $result['status'],
    'retry',
    'payment status after gateway timeout'
);
Resilience::assertLogWritten('warning', 'Payment gateway timeout.');

Resilience::deactivateAll();
```

What this test proves:

- the real `CheckoutService` code runs
- the real container dependency is the one being faulted
- your fallback path is exercised under an injected failure
- you can assert the user-visible or system-visible outcome

Typical workflow:

1. choose the real dependency you want to test
2. inject the failure you want to simulate
3. run the normal application flow
4. assert that the fallback or degraded behavior happened
5. clean up the active fault

## Architecture fit

Laravel Resilience works best when your application already has good dependency seams.

Best-supported patterns:

- dependencies resolved through Laravel container bindings
- contracts or interfaces for critical external services
- outbound integrations wrapped behind service classes
- side effects isolated into jobs, actions, or dedicated services
- Laravel-native HTTP, mail, cache, queue, and storage integrations used through their normal framework entry points

Weakly supported patterns:

- direct `new SomeSdkClient()` calls inside controllers or jobs
- static third-party SDK calls with no container seam
- business logic and IO tightly coupled in the same class
- hidden side effects spread across controllers and listeners

These patterns are still worth scanning with `resilience:discover` and `resilience:suggest`, but they usually need some refactoring before fault injection can be as effective as it is in a well-structured app.

If you want side-by-side examples of those two worlds, see:

- [Example: Well-Structured App](guides/example-well-structured-app.md)
- [Example: Messy Legacy App](guides/example-messy-legacy-app.md)

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

## Manual fault injection

Once you know which flow you want to verify, the usual workflow is:

1. activate a fault for a real dependency
2. run the normal application code
3. assert that the fallback or degraded behavior happened
4. clean up the active fault

Minimal example:

```php
use App\Contracts\PaymentGateway;
use MeShaon\LaravelResilience\Facades\Resilience;

Resilience::for(PaymentGateway::class)->timeout();

expect(fn () => app(PaymentGateway::class)->charge(500))
    ->toThrow(RuntimeException::class, 'Operation timed out.');

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

Current limitation:

- container and Laravel integration proxies currently support `timeout`, `exception`, and `latency` rules

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
php artisan resilience:suggest --include-covered
```

Suggestions include:

- a severity level
- an assessment of `missing`, `partial`, or `covered`
- a short next action for the most likely follow-up work
- evidence already detected in the codebase
- missing signals that may need more work

By default, the command hides `covered` suggestions so the output stays tighter and more actionable. Use `--include-covered` when you want the broader audit view.

## Scaffold command

The scaffold command takes actionable suggestion hotspots and generates draft Pest tests for them.

Run it with:

```bash
php artisan resilience:scaffold
php artisan resilience:scaffold app/Services
php artisan resilience:scaffold --dry-run
php artisan resilience:scaffold --mode=update
php artisan resilience:scaffold --include-covered
```

Scaffold behavior:

- output defaults to `tests/Resilience/Generated`
- a manifest is written to `build/resilience-scaffold.json`
- generated tests are skipped by default until you replace the placeholders with real application flows and assertions
- rerunning in `create` mode skips existing generated files
- rerunning in `update` mode refreshes only managed scaffold files that have not been customized
- `force` mode overwrites managed scaffold files when you explicitly want to regenerate them

This gives developers a starting point without pretending the package can infer the exact fallback assertions for every application.

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
php artisan resilience:discover {path?} [--json] [--category=*] [--compact] [--view=default|compact|verbose] [--html[=path]] [--preview]
php artisan resilience:suggest {path?} [--json] [--category=*] [--include-covered] [--compact] [--view=default|compact|verbose] [--html[=path]] [--preview]
php artisan resilience:scaffold {path?} [--category=*] [--include-covered] [--dry-run] [--mode=create|update|force] [--format=pest] [--output=path] [--manifest=path]
```

## Development

If you want the technical internals instead of the onboarding guide, see [How Laravel Resilience Works](guides/how-laravel-resilience-works.md).

```bash
composer test
composer analyse
composer format
```

## License

MIT. See [LICENSE.md](LICENSE.md).
