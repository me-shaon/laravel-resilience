# Laravel Resilience

Laravel-native resilience testing and fault injection for application-level failure scenarios.

## Status

The package is in active development. The baseline package bootstrapping, rule-based fault model, and first container-based injection path are now in place.

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

The package now has a simple Phase 2 + Phase 3 model:

- a `FaultRule` describes the fault behavior we want
- the `Resilience` registry keeps track of active rules and their attempt counts
- the container wrapper applies those rules to container-managed services and restores the original binding when the rule is removed

### Example

```php
use MeShaon\LaravelResilience\Facades\Resilience;
use App\Contracts\PaymentGateway;

Resilience::for(PaymentGateway::class)->timeout();

$gateway = app(PaymentGateway::class);

$gateway->charge(500); // throws RuntimeException('Operation timed out.')

Resilience::deactivateAll();
```

In this example, Laravel Resilience wraps the `PaymentGateway` container binding, intercepts the method call, applies the active timeout rule, and then lets you restore the original binding with `deactivateAll()` or `deactivate(...)`.

If you want lower-level control, you can still activate rules directly and ask whether they should trigger for a specific attempt, but the intended Phase 3 path is the fluent container API:

```php
Resilience::for(PaymentGateway::class)->timeout();
Resilience::for(SearchClient::class)->process()->exception(new RuntimeException('Search is down.'));
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
