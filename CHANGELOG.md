# Changelog

All notable changes to `laravel-resilience` will be documented in this file.

## v0.5.0 - 2026-03-29

### v0.5.0

#### Summary

- Adds explicit scenario safety guardrails for non-local environments, including dry-run support and a reviewable override ceremony for real fault activation.

#### Highlights

- Added non-local scenario protection so environments outside `local` and `testing` require explicit opt-in and `--confirm-non-local` before a scenario can run.
- Added `--dry-run` support to inspect configured scenarios without activating faults or executing the scenario body.
- Added clearer guardrail errors and audit coverage for dry-run and non-local scenario execution paths.
- Documented the scenario safety model in the README and expanded command and runner coverage around non-local execution.

#### Verification

- composer validate --no-check-publish
- composer test
- composer analyse
- composer format -- --test

#### Full Changelog

- 3d1966b Add scenario safety guardrails

## v0.4.0 - 2026-03-29

### v0.4.0

#### Summary

- Adds an evidence-aware suggestion engine on top of discovery findings so developers can see which resilience safeguards appear to be missing, partially present, or already covered.

#### Highlights

- Added the new `resilience:suggest` command with grouped and JSON output for actionable resilience guidance.
- Added evidence-aware assessments with `missing`, `partial`, and `covered` states plus detected safeguards and missing signals.
- Added guarded discovery fixtures and expanded tests to validate both generic and already-protected code paths.
- Normalized discovery relative paths so command output stays consistent across Unix and Windows CI environments.

#### Verification

- composer validate --no-check-publish
- composer test
- composer analyse
- composer format -- --test

#### Full Changelog

- 90c6f37 Add resilience suggestion engine

## v0.3.0 - 2026-03-28

### v0.3.0

#### Summary

- Adds a discovery scanner that helps developers find resilience-relevant code paths before they have written many resilience tests.

#### Highlights

- Added `php artisan resilience:discover` for scanning application code.
- Added readable and JSON discovery reports with category, file, line, and excerpt details.
- Added detection for practical resilience-sensitive patterns such as HTTP calls, mail sends, queue dispatches, storage writes, cache usage, direct client construction, and concrete client coupling.
- Added fixture-based scanner and command tests.
- Expanded the README with clearer discovery scanner guidance and example output.

#### Verification

- composer validate --no-check-publish
- composer test
- composer analyse
- composer format -- --test

#### Full Changelog

- 6631855 Add resilience discovery scanner
- d33647c Update CHANGELOG

## v0.2.1 - 2026-03-28

### v0.2.1

#### Summary

- Patch release for Laravel Resilience fixing a flaky CI interaction in the scenario command test suite.

#### Highlights

- Removed an unnecessary log spy from the scenario command success test.
- Stabilized the scenario command test path across CI run orders.
- Kept the scenario runner behavior unchanged while preserving the existing logging assertions in the runner tests.

#### Verification

- composer validate --no-check-publish
- composer test
- composer analyse
- composer format -- --test

#### Full Changelog

- 401e70b Stabilize scenario command logging test

## v0.2.0 - 2026-03-28

### v0.2.0

#### Summary

- Adds a configurable scenario runner so named resilience exercises can be executed from Artisan instead of only through ad hoc test setup.

#### Highlights

- Added a scenario contract, runner, and structured run report for reusable resilience workflows.
- Added `php artisan resilience:run {scenario}` with optional `--json` output.
- Added structured logging for scenario runs so results are visible and auditable.
- Added tests for successful runs, failed runs, blocked environments, and command output.
- Expanded the README with clearer scenario runner documentation and a concrete example.

#### Verification

- composer validate --no-check-publish
- composer test
- composer analyse
- composer format -- --test

#### Full Changelog

- 60a6e84 Add scenario runner and command support
- add3598 Update README installation instructions
- dda8ff0 Update CHANGELOG
- 6177f6b Merge remote-tracking branch 'origin/main'
- a45bd28 Update CHANGELOG

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
