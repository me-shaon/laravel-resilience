# Example: Well-Structured App

This example shows where Laravel Resilience fits best: an application with clear container seams, contracts for external services, and fallback behavior that already lives in dedicated service classes.

## The application shape

Imagine an app with:

- `App\Contracts\PaymentGateway`
- `App\Services\CheckoutService`
- a concrete gateway bound in a service provider
- fallback behavior handled inside the service, not inside the controller

Example binding:

```php
use App\Contracts\PaymentGateway;
use App\Integrations\StripePaymentGateway;

$this->app->bind(PaymentGateway::class, StripePaymentGateway::class);
```

Example service:

```php
use App\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class CheckoutService
{
    public function __construct(
        private PaymentGateway $payments
    ) {}

    public function charge(int $amount): array
    {
        try {
            $this->payments->charge($amount);

            return ['status' => 'paid'];
        } catch (RuntimeException $exception) {
            Log::warning('Payment gateway timeout.', ['amount' => $amount]);

            return ['status' => 'retry'];
        }
    }
}
```

## Why this app shape works well

Laravel Resilience can intercept the same container binding your application uses in production.

That means you can test:

- real service wiring
- real fallback behavior
- real log and event output
- real degraded responses

without replacing the dependency with a test double in the test itself.

## Example resilience test

```php
use App\Contracts\PaymentGateway;
use App\Services\CheckoutService;
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

## What this proves

- the fault was injected into the real dependency seam
- the application switched to the intended fallback path
- the test still exercised normal container resolution
- the behavior stayed close to a real runtime failure path

## Example scenario

If the same flow matters operationally, you can package it as a named scenario:

```php
'scenarios' => [
    'payment-fallback' => App\Resilience\PaymentFallbackScenario::class,
],
```

Then run:

```bash
php artisan resilience:run payment-fallback
```

That gives the team a repeatable resilience drill instead of a one-off test setup.

## Example discovery and suggestion flow

For a well-structured app, discovery and suggestion are most useful when:

- onboarding the package
- auditing a new subsystem
- checking for missing resilience coverage after refactors

Typical workflow:

```bash
php artisan resilience:discover app/Services
php artisan resilience:suggest app/Services
php artisan resilience:scaffold app/Services --dry-run
```

In a clean architecture, the suggestions often become:

- add a resilience scenario for this service
- add timeout or fallback coverage here
- verify duplicate-side-effect protection around this queue path

rather than broad refactoring advice.

From there, `resilience:scaffold` can generate draft tests for the still-actionable hotspots, which usually means the team only needs to fill in the real application flow and final assertions.

## Takeaway

Laravel Resilience gives the most value when the application already exposes dependencies through stable seams.

If your app already uses:

- contracts
- service classes
- container bindings
- isolated side effects

then the package can usually be adopted quickly and provide high-confidence resilience tests with minimal friction.
