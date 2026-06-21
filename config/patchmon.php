<?php

return [

    /*
    |--------------------------------------------------------------------------
    | NetBox sync
    |--------------------------------------------------------------------------
    |
    | Connection details for the NetBox install that acts as the canonical
    | inventory of the server estate.
    |
    */

    'netbox' => [
        'base_url' => env('NETBOX_BASE_URL'),
        'key' => env('NETBOX_API_KEY'),
        'token' => env('NETBOX_API_TOKEN'),
        'verify_tls' => env('NETBOX_VERIFY_TLS', true),
        'timeout' => env('NETBOX_TIMEOUT', 10),

        'change_ratio' => env('NETBOX_CHANGE_RATIO', 0.5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Triage defaults
    |--------------------------------------------------------------------------
    |
    | The default patching cadence applied to servers created automatically
    | with no team yet — both the NetBox sync and the record_patched.sh
    | first-run provision endpoint land servers in triage for a human to
    | refine, so they share these conservative defaults.
    |
    */

    'triage_defaults' => [
        'interval_months' => env('TRIAGE_DEFAULT_INTERVAL_MONTHS', 1),
        'grace_value' => env('TRIAGE_DEFAULT_GRACE_VALUE', 7),
        'grace_units' => env('TRIAGE_DEFAULT_GRACE_UNITS', 'days'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Prometheus metrics endpoint
    |--------------------------------------------------------------------------
    |
    | Static bearer token Prometheus presents to scrape /metrics. Leave unset
    | to disable the endpoint — it returns 503 until a token is configured.
    |
    */

    'metrics' => [
        'token' => env('PATCHMON_METRICS_TOKEN'),
    ],

];
