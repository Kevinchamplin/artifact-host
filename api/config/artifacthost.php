<?php

return [
    'apex' => env('ARTIFACTHOST_APEX', 'hosting.champlinenterprises.com'),

    // The Plesk subscription that owns the apex. Equals apex if apex is itself a
    // primary domain. If apex is a Plesk subdomain (e.g. hosting.<root>.com is a
    // subdomain of <root>.com) this must be the parent — vhost paths nest under
    // /var/www/vhosts/<subscription_domain>/. Lowercase (filesystem path).
    'subscription_domain' => env('ARTIFACTHOST_SUBSCRIPTION_DOMAIN', 'champlinenterprises.com'),

    // Plesk stores the primary domain case-sensitively and the CLI flag
    // `-domain` is exact-match. Defaults to subscription_domain.
    'plesk_subscription_domain' => env('ARTIFACTHOST_PLESK_SUBSCRIPTION_DOMAIN'),

    'le_email' => env('ARTIFACTHOST_LE_EMAIL', 'admin@champlinenterprises.com'),

    'plesk' => [
        'host' => env('PLESK_HOST', '127.0.0.1'),
        'port' => env('PLESK_PORT', 8443),
        'api_key' => env('PLESK_API_KEY'),
    ],

    'limits' => [
        'max_file_bytes' => env('ARTIFACTHOST_MAX_FILE_BYTES', 5 * 1024 * 1024),
        'max_total_bytes' => env('ARTIFACTHOST_MAX_TOTAL_BYTES', 100 * 1024 * 1024),
    ],
];
