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
];
