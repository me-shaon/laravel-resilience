# Example: Messy Legacy App

This example shows how Laravel Resilience can still help in a codebase that does not yet have clean dependency seams.

The package is more limited here, but discovery and suggestion can still guide practical improvements.

## The application shape

Imagine a controller like this:

```php
use Legacy\Payments\LegacyGatewayClient;
use Illuminate\Support\Facades\Cache;

final class CheckoutController
{
    public function __invoke(): array
    {
        $gateway = new LegacyGatewayClient(
            config('services.legacy-payments.key')
        );

        $result = $gateway->charge(500);

        Cache::put('checkout.last_result', $result, 60);

        return ['status' => $result->status()];
    }
}
```

This code has a few common legacy problems:

- direct `new` of an external client
- business logic and integration logic in the same class
- side effects mixed into the controller
- no clear contract or service seam to intercept

## What Laravel Resilience can still do immediately

Even before you refactor, the package can still help you identify risky areas.

### Discovery

```bash
php artisan resilience:discover app/Http/Controllers
```

Likely findings:

- `client-construction`
- `cache`
- possibly `http` or `queue` if those calls are also present

### Suggestion

```bash
php artisan resilience:suggest app/Http/Controllers
```

Likely suggestions:

- wrap this external client behind a service boundary
- introduce a contract for this concrete dependency
- add resilience coverage around this fallback or cache path

In a legacy app, this guidance is often the first useful outcome.

## What is harder right away

Direct runtime fault injection is weaker here because there is no container-managed seam for the external client.

That means:

- `Resilience::for(LegacyGatewayClient::class)->timeout()` will not help much if the code creates the object with `new`
- the package cannot reliably intercept a static third-party SDK call with no framework seam

This is why Laravel Resilience is intentionally honest about weakly supported patterns.

## A practical refactor path

You usually do not need a large rewrite.
One small seam is often enough to start getting value.

Step 1:
extract the gateway behind an interface and service

```php
interface PaymentGateway
{
    public function charge(int $amount): PaymentResult;
}
```

Step 2:
move the legacy client usage into an adapter

```php
use Legacy\Payments\LegacyGatewayClient;

final class LegacyPaymentGatewayAdapter implements PaymentGateway
{
    public function __construct(
        private LegacyGatewayClient $client
    ) {}

    public function charge(int $amount): PaymentResult
    {
        return $this->client->charge($amount);
    }
}
```

Step 3:
inject the contract into application code

```php
final class CheckoutService
{
    public function __construct(
        private PaymentGateway $payments
    ) {}
}
```

Once that seam exists, Laravel Resilience becomes much more effective.

## Example after a small refactor

```php
use App\Contracts\PaymentGateway;
use MeShaon\LaravelResilience\Facades\Resilience;

Resilience::for(PaymentGateway::class)->timeout();
```

Now the package can inject into the real dependency path because the application resolves the dependency through Laravel’s container.

## Recommended legacy adoption path

For a messy codebase, the best path is usually:

1. run `resilience:discover`
2. run `resilience:suggest`
3. run `resilience:scaffold --dry-run` to preview draft tests for the most actionable hotspots
4. pick one high-value failure path
5. create one container seam around it
6. turn one generated draft into a focused resilience test

That is enough to start getting value without demanding a whole-application rewrite.

## Takeaway

Laravel Resilience is still useful in a legacy app, but the first value often comes from:

- visibility
- prioritization
- targeted refactoring guidance

not from immediate broad fault injection coverage everywhere.

In other words:

- use discovery and suggestions to find the most important seams to create
- then use fault injection once those seams exist
