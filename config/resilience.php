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
];
