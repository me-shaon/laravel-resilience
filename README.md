# Laravel Resilience

Laravel-native resilience testing and fault injection for application-level failure scenarios.

## Status

The package is in active bootstrap. Phase 0 establishes the real package baseline before the v1 feature work begins.

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

## Current fault model

Phase 2 uses a simple rule-based model:

- a `FaultTarget` identifies what we want to affect
- a `FaultRule` describes the fault behavior and when it should trigger
- the `Resilience` registry stores active rules and answers whether a rule should fire for a given attempt

### Example

```php
use MeShaon\LaravelResilience\Facades\Resilience;
use MeShaon\LaravelResilience\Faults\FaultRule;
use MeShaon\LaravelResilience\Faults\FaultScope;
use MeShaon\LaravelResilience\Faults\FaultTarget;

$target = FaultTarget::container('payment-gateway');

$rule = FaultRule::failFirst(
    name: 'payment-gateway-fails-twice',
    target: $target,
    attempts: 2,
    scope: FaultScope::Test,
);

Resilience::activate($rule);

Resilience::shouldActivate($target, 1); // true
Resilience::shouldActivate($target, 2); // true
Resilience::shouldActivate($target, 3); // false

Resilience::deactivate($target);
```

In this example, the rule is active only for the first two attempts against the `payment-gateway` target. Phase 2 only decides whether the rule should fire; later phases will connect that decision to real container and Laravel integration hooks.

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
