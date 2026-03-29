<?php

return [
    /*
     * Global kill switch for runtime activation.
     */
    'enabled' => (bool) env('RESILIENCE_ENABLED', true),

    /*
     * Runtime activation is blocked in these environments.
     *
     * Remove 'production' from this list if you explicitly want to allow it.
     */
    'blocked_environments' => ['production'],

    /*
     * Named scenario classes that can be executed through the scenario runner
     * and the `php artisan resilience:run {scenario}` command.
     */
    'scenarios' => [
        // 'search-fallback' => \App\Resilience\SearchFallbackScenario::class,
    ],

    /*
     * Scenario runner safety rules.
     *
     * Scenario execution is considered safe by default in local-style environments.
     * Any other environment requires explicit opt-in plus the `--confirm-non-local`
     * command flag before the runner will activate faults.
     *
     * Use `--dry-run` to inspect a scenario without activating faults or executing
     * the scenario body.
     */
    'scenario_runner' => [
        'safe_environments' => ['local', 'testing'],
        'allow_non_local' => (bool) env('RESILIENCE_ALLOW_NON_LOCAL_SCENARIOS', false),
    ],

    /*
     * Default paths for resilience discovery scans. The discovery command
     * scans the application path by default, but these values document the
     * expected source locations for future expansion.
     */
    'discovery' => [
        'paths' => ['app'],
    ],
];
