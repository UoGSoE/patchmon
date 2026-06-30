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
        'default_domain' => env('NETBOX_DEFAULT_DOMAIN', 'example.ac.uk'),

        /*
        | The resolvable departmental subdomains under default_domain, used to
        | recover the FQDN of a bare hostname during the cleanup pass: a bare
        | name is tried as host.<subdomain>.<default_domain> against DNS. Each
        | listed subdomain resolves in its own right, so it is never treated as
        | an alias for another.
        */
        'subdomains' => ['eng', 'elec', 'civil', 'physics', 'ppe', 'astro', 'maths', 'chem', 'geog', 'cose'],

        /*
        | Single-department buildings act as a safe prior when recovering a bare
        | hostname's FQDN: a host in one of these is tried only against that
        | building's subdomain(s), cutting DNS lookups and avoiding false
        | ambiguity. Buildings NOT listed here — shared buildings, data centres,
        | and hosts with no site (most VMs) — fall back to the full subdomain
        | set. Keys must match NetBox's site.name exactly.
        */
        'building_departments' => [
            'James Watt South' => ['eng', 'elec', 'civil'],
            'Rankine Building' => ['eng', 'elec', 'civil'],
            'Boyd Orr Building' => ['maths'],
            'Maths & Stats Building' => ['maths'],
            'Joseph Black Building' => ['chem'],
            'Observatory' => ['astro'],
        ],

        /*
        | Department tokens that aren't resolvable subdomains in their own right
        | but belong to one that is: a "host.<alias>" name is proposed as
        | "host.<canonical>.<default_domain>". A token that is neither a subdomain
        | nor listed here is left to flag for a human.
        */
        'department_aliases' => ['cognition' => 'cose'],
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
