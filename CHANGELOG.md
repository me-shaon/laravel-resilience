# Changelog

All notable changes to `laravel-resilience` will be documented in this file.

## v0.1.1 - 2026-03-28

### v0.1.1

#### Summary

- Patch release for Laravel Resilience fixing a CI-only failure in the HTTP facade timeout test.

#### Highlights

- Updated the HTTP facade timeout test to avoid Laravel's fake response promise path.
- Removed the test's implicit dependency on `guzzlehttp/promises` for matrix rows where that package was not present.
- Kept the test coverage intent the same: the timeout fault still triggers before any HTTP request is allowed through.

#### Verification

- composer validate --no-check-publish
- composer test
- composer analyse
- composer format -- --test

#### Full Changelog

- 13afecf Fix HTTP facade timeout test without Guzzle promises

## v0.1.0 - 2026-03-28

### v0.1.0

#### Summary

- First public release of Laravel Resilience, a Laravel-native fault injection and resilience testing toolkit for application-level failure scenarios.

#### Highlights

- Added a rule-based fault model with deterministic activation behavior.
- Added container fault injection for application services and contracts.
- Added Laravel-native helpers for HTTP, mail, cache, queue, and storage fault injection.
- Added support for targeting named Laravel stores, mailers, queue connections, and disks without affecting the default driver.
- Added a first assertion layer for fallbacks, logs, events, jobs, degraded-success responses, and duplicate side-effect checks.

#### Verification

- composer validate --no-check-publish
- composer test
- composer analyse
- composer format -- --test

#### Full Changelog

- d5b0faa Prepare v0.1.0 release
- d02c2bf Add resilience assertion helpers and tests
- 2bb5e90 Support named Laravel service targets
- cf2f35a Fix Windows CI by running test workflow steps in bash
- dffe151 Add container fault injection and simplify rule tracking
- 2f4031c Add simplified fault rule model and phase 2 docs
- 7f01ad8 fix: github CI tests
- cc6c0b3 chore: Resilience base config and class setup
- 053502d chore: package core setup
- e7a22cb Initial commit
