# Laravel Resilience Usage Guide

This guide keeps the longer explanations, examples, and workflow notes that used to live in the README.

Use the README for a quick overview.
Use this guide when you want a deeper understanding of how to adopt the package in a real project.

## The easiest onboarding path

You do not need to begin by writing resilience tests from scratch.

The easiest adoption flow is:

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

This helps teams answer:

- which parts of the app are most likely to fail in real life?
- where should resilience tests be added first?
- which flows already look partially protected?

## Report options

For larger projects, you do not have to stay with the default terminal layout.

Output options:

- default output uses grouped tables in the terminal for a readable overview
- `--compact` flattens the report into a denser table
- `--view=verbose` keeps the grouped layout and adds excerpts plus richer signal detail
- `resilience:suggest` hides `covered` suggestions by default so the output stays focused on work that is still likely worth doing
- `--include-covered` brings already-covered suggestions back when you want a broader audit view
- `--html` writes a standalone HTML report under `build/resilience-reports` by default
- `--html=path/to/report.html` writes the HTML report to a specific location
- `--preview` prints a browser-ready `file://` URL for the generated HTML report

Examples:

```bash
php artisan resilience:discover --compact
php artisan resilience:suggest --view=verbose
php artisan resilience:suggest --include-covered
php artisan resilience:discover --html
php artisan resilience:suggest --html=build/resilience-reports/suggest.html --preview
```

HTML reports are especially useful when the CLI output gets too large to review comfortably. They include search, category filters, and copy actions for AI-ready prompts.

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

- [Example: Well-Structured App](example-well-structured-app.md)
- [Example: Messy Legacy App](example-messy-legacy-app.md)

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
    ->toThrow(\RuntimeException::class, 'Operation timed out.');

Resilience::deactivateAll();
```

## Fault injection patterns

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

## Scenario runner

Scenarios let you define a named resilience exercise and rerun it from Artisan.

Register scenarios in `config/resilience.php`:

```php
'scenarios' => [
    'payment-fallback' => App\Resilience\PaymentFallbackScenario::class,
],
```

Run them with:

```bash
php artisan resilience:run payment-fallback
php artisan resilience:run payment-fallback --json
php artisan resilience:run payment-fallback --dry-run
RESILIENCE_ALLOW_NON_LOCAL_SCENARIOS=true php artisan resilience:run payment-fallback --confirm-non-local
```

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
