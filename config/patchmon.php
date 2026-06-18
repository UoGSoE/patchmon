<?php

return [

    /*
    |--------------------------------------------------------------------------
    | NetBox sync
    |--------------------------------------------------------------------------
    |
    | Connection details for the NetBox install that acts as the canonical
    | inventory of the server estate, plus the default patching cadence applied
    | to servers created by the sync (they land in triage for a human to refine).
    |
    */

    'netbox' => [
        'base_url' => env('NETBOX_BASE_URL'),
        'token' => env('NETBOX_API_TOKEN'),
        'verify_tls' => env('NETBOX_VERIFY_TLS', true),
        'timeout' => env('NETBOX_TIMEOUT', 10),

        'default_interval_months' => env('NETBOX_DEFAULT_INTERVAL_MONTHS', 1),
        'default_grace_value' => env('NETBOX_DEFAULT_GRACE_VALUE', 7),
        'default_grace_units' => env('NETBOX_DEFAULT_GRACE_UNITS', 'days'),
    ],

];
