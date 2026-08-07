<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://okuruu.vercel.app',
        'https://layescent.vercel.app',
        'https://kosada.vercel.app',
    ],

    /*
    | Vercel gives every preview deployment its own hostname, and none of them
    | match the production aliases above, so previews used to get no CORS
    | headers at all. Local dev hits this same API (see baseConfig), so
    | localhost needs to match too.
    */
    'allowed_origins_patterns' => [
        '#^https://(okuruu|layescent|kosada)-[a-z0-9-]+\.vercel\.app$#',
        '#^http://localhost(:\d+)?$#',
        '#^http://127\.0\.0\.1(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    // Was 0, which forbade preflight caching: every JSON POST cost two round
    // trips. 7200 is the ceiling Chrome honours.
    'max_age' => 7200,

    'supports_credentials' => true,

];
