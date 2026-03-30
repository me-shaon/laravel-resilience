# How Laravel Resilience Works

This guide explains how Laravel Resilience works internally so you can understand the package beyond the README examples.

Use the README for onboarding and day-to-day usage.
Use this document when you want to understand the package architecture, extension points, and current limitations.

## Big picture

Laravel Resilience is built around one core idea:

- activate a fault rule against a real dependency seam
- let normal application code run
- observe the real fallback, retry, logging, event, job, or degraded-response behavior

The package does not try to replace all of Laravel testing.
Instead, it adds a deterministic failure layer on top of normal container resolution, Laravel integrations, scenarios, discovery, suggestion, and scaffold workflows.

## Main subsystems

The package is easiest to understand as seven cooperating subsystems:

1. fault modeling
2. fault activation and tracking
3. runtime injection
4. scenario execution
5. discovery and suggestion analysis
6. scaffold generation
7. safety guardrails

## 1. Fault modeling

The fault model lives primarily in:

- [FaultRule.php](../src/Faults/FaultRule.php)
- [FaultTarget.php](../src/Faults/FaultTarget.php)
- [FaultScope.php](../src/Faults/FaultScope.php)
- [FaultType.php](../src/Faults/FaultType.php)

These classes answer four questions:

- what should fail
- how should it fail
- how long should that rule stay active
- how should attempt-based behavior be evaluated

### FaultRule

`FaultRule` is the main unit of fault configuration.

It contains:

- a rule name
- a target
- a type
- optional type-specific metadata such as latency duration, exception instance, or attempt count
- a scope, usually `test` or `process`

Examples:

- `FaultRule::timeout(...)`
- `FaultRule::exception(...)`
- `FaultRule::latency(...)`
- `FaultRule::failFirst(...)`
- `FaultRule::recoverAfter(...)`
- `FaultRule::percentage(...)`
- `FaultRule::percentageOnAttempts(...)`

### FaultTarget

`FaultTarget` identifies what a rule applies to.

Today, the most important target type is `container`, because runtime injection is implemented around Laravel container-managed services and Laravel integration roots.

Examples:

- `FaultTarget::container(App\Contracts\PaymentGateway::class)`
- `FaultTarget::container('cache')`
- `FaultTarget::integration('payment-gateway')`

### FaultScope

`FaultScope` controls the intended lifetime of a rule:

- `test`
- `process`

This allows the fault manager to clear only one category of active rules when needed.

## 2. Fault activation and tracking

The active fault registry lives in:

- [FaultManager.php](../src/Faults/FaultManager.php)
- [LaravelResilience.php](../src/LaravelResilience.php)
- [ContainerFaultBuilder.php](../src/ContainerFaultBuilder.php)

### FaultManager

`FaultManager` is the in-memory registry of active rules.

It is responsible for:

- storing active rules by target
- replacing earlier rules for the same target when a new one is activated
- tracking attempts for deterministic evaluation
- deciding whether a rule should fire on a given attempt
- clearing rules by target, scope, or all at once

This separation matters because the package needs one place to answer:

- what is active right now?
- for this target, on this attempt, should the rule trigger?

### LaravelResilience facade root

`LaravelResilience` is the package façade root and the user-facing coordination layer.

It:

- delegates activation and lookup to `FaultManager`
- delegates runtime injection to `ContainerFaultInjector`
- exposes friendly helpers like `for()`, `http()`, `mail()`, `cache()`, `queue()`, and `storage()`
- exposes assertion helpers
- exposes guard checks such as `ensureCanActivate()` and `ensureCanRunScenario()`

### ContainerFaultBuilder

`ContainerFaultBuilder` is the small fluent API object returned by calls like:

```php
Resilience::for(App\Contracts\PaymentGateway::class)
Resilience::cache('redis')
Resilience::mail('ses')
```

Its job is simple:

- collect the target and scope
- turn fluent calls like `timeout()` or `latency(40)` into concrete `FaultRule` instances
- activate those rules through `LaravelResilience`

## 3. Runtime injection

Runtime injection is centered on:

- [ContainerFaultInjector.php](../src/Faults/Injectors/ContainerFaultInjector.php)

This is the layer that makes Laravel Resilience different from plain mocking.

Instead of swapping application behavior with a fake in the test itself, the injector wraps the real container binding that the application normally resolves.

### How container injection works

At a high level:

1. a fault rule is activated for a container target
2. the injector snapshots the original container binding
3. the injector rebinds that abstract to an instrumented wrapper
4. application code resolves the dependency normally from the container
5. method calls are intercepted
6. the fault manager decides whether the active rule should fire
7. if it should fire, the injector throws, delays, or times out
8. if it should not fire, the call is forwarded to the original dependency
9. when the rule is deactivated, the original binding is restored

### What is currently supported at runtime

Direct runtime proxy injection currently supports:

- `timeout`
- `exception`
- `latency`

The richer rule model still exists for deterministic evaluation and custom use cases, but the runtime proxy path is intentionally narrower for now.

### Laravel integration helpers

The package’s Laravel-specific helpers are thin wrappers over the same container injection path.

Examples:

- `Resilience::http()`
- `Resilience::mail()`
- `Resilience::cache()`
- `Resilience::queue()`
- `Resilience::storage()`

These helpers target known Laravel container roots such as:

- HTTP client factory
- mail manager
- cache manager
- queue manager
- filesystem manager

Named driver support works by using container target names such as:

- `cache::redis`
- `mail.manager::ses`
- `queue::redis`
- `filesystem::s3`

That lets the package fault a specific named store, connection, mailer, or disk without affecting the default one.

## 4. Scenario execution

Scenario execution is centered on:

- [ResilienceScenario.php](../src/Scenarios/ResilienceScenario.php)
- [ScenarioRunner.php](../src/Scenarios/ScenarioRunner.php)
- [ScenarioRunReport.php](../src/Scenarios/ScenarioRunReport.php)
- [RunResilienceScenarioCommand.php](../src/Commands/RunResilienceScenarioCommand.php)

### What a scenario is

A scenario is a named resilience exercise that combines:

- fault rules to activate
- real application code to execute
- a structured result to report

This makes scenarios a good fit for:

- repeatable resilience drills
- non-trivial fallback workflows
- operational or pre-release verification

### How a scenario run works

When `php artisan resilience:run some-scenario` runs:

1. the configured scenario is resolved from `config/resilience.php`
2. environment guardrails are checked
3. the scenario’s fault rules are collected
4. if `--dry-run` is enabled, the runner reports what would happen and stops
5. otherwise the rules are activated
6. the scenario body runs
7. a `ScenarioRunReport` is built
8. an audit log entry is written
9. all active faults are cleaned up

### Why ScenarioRunReport exists

`ScenarioRunReport` gives one structured object for:

- command output
- JSON output
- logging
- tests

That keeps the scenario runner simpler than having separate result shapes for each output mode.

## 5. Discovery and suggestion analysis

This package has two analysis-oriented subsystems:

- discovery
- suggestion

### Discovery scanner

Discovery lives in:

- [DiscoveryScanner.php](../src/Discovery/DiscoveryScanner.php)
- [DiscoveryFinding.php](../src/Discovery/DiscoveryFinding.php)
- [DiscoveryReport.php](../src/Discovery/DiscoveryReport.php)
- [DiscoverResilienceRisksCommand.php](../src/Commands/DiscoverResilienceRisksCommand.php)

The discovery scanner is intentionally heuristic-based.

It works by:

1. walking PHP files under the selected base path
2. reading the file contents as text
3. matching supported regex patterns
4. recording category, path, line, and excerpt for each finding

Current finding categories include:

- `http`
- `mail`
- `queue`
- `storage`
- `cache`
- `client-construction`
- `concrete-dependency`

This scanner does not claim certainty.
It is meant to answer:

- where in this codebase are the likely resilience-sensitive seams?

not:

- where are the guaranteed bugs?

### Suggestion engine

Suggestions live in:

- [SuggestionEngine.php](../src/Suggestions/SuggestionEngine.php)
- [ResilienceSuggestion.php](../src/Suggestions/ResilienceSuggestion.php)
- [SuggestionReport.php](../src/Suggestions/SuggestionReport.php)
- [SuggestResilienceImprovementsCommand.php](../src/Commands/SuggestResilienceImprovementsCommand.php)

The suggestion engine builds on discovery findings instead of rescanning the codebase from scratch.

At a high level it:

1. runs discovery for the target path and categories
2. maps each discovery category to a conservative recommendation
3. inspects the matched file for resilience signals such as:
   - timeout handling
   - retry handling
   - local fallback or exception handling
   - duplicate-side-effect protection
4. looks for related test or scenario references
5. classifies the result as:
   - `missing`
   - `partial`
   - `covered`
6. emits grouped suggestions with evidence and missing signals

This means the command can now say more than:

- this looks risky

It can also say:

- some safeguards are already visible here
- these signals still appear to be missing

That makes the output more useful during architectural review and test planning.

## 6. Scaffold generation

Scaffolding lives in:

- [ScaffoldResilienceTestsCommand.php](../src/Commands/ScaffoldResilienceTestsCommand.php)
- [ResilienceTestScaffolder.php](../src/Scaffolding/ResilienceTestScaffolder.php)
- [ScaffoldReport.php](../src/Scaffolding/ScaffoldReport.php)
- [ScaffoldedItem.php](../src/Scaffolding/ScaffoldedItem.php)

The scaffold command builds on actionable suggestions instead of working directly from raw discovery findings.

At a high level it:

1. runs the suggestion engine for the selected path and categories
2. skips `covered` hotspots by default unless `--include-covered` is requested
3. assigns deterministic hotspot IDs and output paths
4. writes draft Pest tests into a dedicated generated-test directory
5. records generated hashes in a scaffold manifest
6. avoids overwriting customized generated files unless `--mode=force` is used

This is intentionally conservative.

The package can suggest likely resilience gaps, but it cannot know every application's correct fallback behavior.
So scaffolding is designed to give you a strong starting point, not a false sense of finished resilience coverage.

## 7. Safety guardrails

Safety enforcement is centered on:

- [EnvironmentGuard.php](../src/Support/EnvironmentGuard.php)
- [ActivationNotAllowed.php](../src/Exceptions/ActivationNotAllowed.php)

### Global activation safety

At the broadest level:

- `resilience.enabled` is the global kill switch
- `resilience.blocked_environments` blocks activation in environments like `production`

If activation is blocked, the package fails loudly with `ActivationNotAllowed`.

### Scenario-specific safety

Scenarios have stricter rules than ordinary local tests.

By default:

- `local` and `testing` are considered safe environments for scenario execution
- other environments require explicit opt-in

Non-local scenario execution requires both:

- `RESILIENCE_ALLOW_NON_LOCAL_SCENARIOS=true`
- `--confirm-non-local`

There is also:

- `--dry-run`

Dry runs are intentionally safe:

- no fault rules are activated
- the scenario body is not executed
- an auditable dry-run report is still produced

This keeps non-local usage explicit and reviewable.

## Assertions and testing philosophy

Laravel Resilience testing is meant to stay close to normal Laravel test style.

The package does not require a custom test harness.
Instead, the common workflow is:

1. activate a fault
2. run the real application flow
3. assert the resulting behavior

The assertion helpers in [LaravelResilience.php](../src/LaravelResilience.php) are intentionally small and composable.

They focus on common resilience outcomes like:

- fallback used
- log written
- event dispatched
- job dispatched
- degraded but successful response
- no duplicate side effects

## Best-supported patterns

Laravel Resilience works best when the application already has clear seams:

- dependencies resolved through container bindings
- interfaces or contracts for critical external services
- application services wrapping outbound integrations
- side effects isolated into services, jobs, or actions
- Laravel-native integrations used through standard framework abstractions

## Weakly supported patterns

The package is intentionally honest about weaker support.

The following patterns are harder to intercept or reason about:

- direct `new SomeSdkClient()` calls inside controllers or jobs
- static third-party SDK calls with no container seam
- business logic and IO tightly coupled in the same class
- hidden side effects scattered across controllers and listeners

These cases are still useful for discovery and suggestion output, but fault injection support is weaker until the application gains better seams.

## A practical mental model

If you want to keep the package architecture in your head simply, use this model:

- `FaultRule` says what should happen
- `FaultManager` says what is active and when it should trigger
- `ContainerFaultInjector` applies that rule to a real runtime dependency seam
- `ScenarioRunner` lets you package a repeatable named experiment
- `DiscoveryScanner` finds likely resilience-sensitive code
- `SuggestionEngine` explains what seems missing or already covered
- `EnvironmentGuard` keeps risky execution paths explicit

That is the core of how Laravel Resilience works today.
