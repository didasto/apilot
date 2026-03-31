<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Globales Route-Prefix
    |--------------------------------------------------------------------------
    | Prefix für alle vom Package registrierten API-Routen.
    | Beispiel: 'api/v1' ergibt Routen wie /api/v1/posts
    */
    'prefix' => 'api',

    /*
    |--------------------------------------------------------------------------
    | Globale Middleware
    |--------------------------------------------------------------------------
    | Middleware, die auf ALLE vom Package registrierten Routen angewendet wird.
    */
    'middleware' => ['api'],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */
    'pagination' => [
        'default_per_page' => 15,
        'max_per_page'     => 100,
        'per_page_param'   => 'per_page',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sorting
    |--------------------------------------------------------------------------
    */
    'sorting' => [
        'param'             => 'sort',
        'default_direction' => 'asc',
    ],

    /*
    |--------------------------------------------------------------------------
    | Filtering
    |--------------------------------------------------------------------------
    */
    'filtering' => [
        'param' => 'filter',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Response Wrapper
    |--------------------------------------------------------------------------
    | Der Key, unter dem Daten im JSON-Response gewrapped werden.
    | null = kein Wrapping (Laravel-Resource-Default wird genutzt).
    */
    'response_wrapper' => 'data',

    /*
    |--------------------------------------------------------------------------
    | Auto-Discovery für #[ApiResource] Attribute
    |--------------------------------------------------------------------------
    */
    'auto_discover' => [

        /*
        | Aktiviert das automatische Scannen von Controller-Verzeichnissen
        | nach #[ApiResource] Attributen.
        */
        'enabled' => false,

        /*
        | Verzeichnisse die gescannt werden sollen.
        | Jeder Eintrag ist ein Array mit 'directory' und 'namespace'.
        */
        'directories' => [
            [
                'directory' => null, // z.B. app_path('Http/Controllers/Api')
                'namespace' => 'App\\Http\\Controllers\\Api',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAPI Specification
    |--------------------------------------------------------------------------
    */
    'openapi' => [

        /*
        | Aktiviert/deaktiviert die /api/doc Route.
        */
        'enabled' => true,

        /*
        | Route-Pfad für die Live-Spec (relativ zum globalen Prefix).
        | Ergibt z.B. /api/doc bei prefix='api'.
        */
        'path' => 'doc',

        /*
        | Middleware für die Doc-Route.
        */
        'middleware' => ['api'],

        /*
        | Info-Block der OpenAPI-Spec.
        */
        'info' => [
            'title'       => env('APP_NAME', 'API') . ' Documentation',
            'description' => 'Auto-generated API documentation.',
            'version'     => '1.0.0',
        ],

        /*
        | Server-URLs für die Spec.
        | Wenn leer, wird automatisch die APP_URL verwendet.
        */
        'servers' => [],

        /*
        | Standard-Security-Schema.
        | Wird auf alle Pfade angewendet, die eine Auth-Middleware haben.
        | Unterstützt: 'bearer', 'basic', 'apiKey', null (kein Security).
        */
        'default_security' => 'bearer',

        /*
        | Pfad für den Artisan-Export.
        */
        'export_path' => storage_path('app/openapi.json'),

    ],

];
