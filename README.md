# Laravel Resilience

Laravel-native resilience testing and fault injection for application-level failure scenarios.

## Status

The package is in active bootstrap. Phase 0 establishes the real package baseline before the v1 feature work begins.

## Compatibility

- PHP 8.1+
- Laravel 10, 11, 12, and 13

## Planned capabilities

- deterministic fault injection for container-managed services and Laravel integrations
- resilience-oriented assertions for fallbacks, logs, events, jobs, and duplicate side effects
- discovery tooling that highlights resilience-sensitive touchpoints and suggests practical improvements

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
