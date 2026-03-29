# Configuration

Publish the config file to customize Apilot's behavior:

```bash
php artisan vendor:publish --tag=apilot
```

This creates `config/apilot.php` in your application.

## Full Reference

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    | Applied to all routes registered by CrudRouteRegistrar.
    |
    | Default: 'api'
    | Example: 'api/v2' → routes at /api/v2/posts
    */
    'prefix' => 'api',

    /*
    |--------------------------------------------------------------------------
    | Global Middleware
    |--------------------------------------------------------------------------
    | Applied to every route registered by CrudRouteRegistrar.
    | Per-route middleware (->middleware([...])) is merged on top of this.
    |
    | Default: ['api']
    */
    'middleware' => ['api'],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */
    'pagination' => [
        // Default number of items per page when ?per_page is not specified.
        'default_per_page' => 15,

        // Maximum allowed ?per_page value. Requests above this are capped.
        'max_per_page' => 100,

        // Query parameter name for per-page count.
        'per_page_param' => 'per_page',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sorting
    |--------------------------------------------------------------------------
    */
    'sorting' => [
        // Query parameter name for sort fields.
        // ?sort=title,-created_at → sort by title asc, then created_at desc
        'param' => 'sort',

        // Default sort direction when no direction prefix is specified.
        'default_direction' => 'asc',
    ],

    /*
    |--------------------------------------------------------------------------
    | Filtering
    |--------------------------------------------------------------------------
    */
    'filtering' => [
        // Query parameter name for filters.
        // ?filter[status]=published
        'param' => 'filter',
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Wrapper
    |--------------------------------------------------------------------------
    | The key under which data is wrapped in JSON responses.
    | 'data' → { "data": [...] }
    | null  → uses Laravel's default Resource wrapping
    */
    'response_wrapper' => 'data',

    /*
    |--------------------------------------------------------------------------
    | OpenAPI Specification
    |--------------------------------------------------------------------------
    */
    'openapi' => [

        // Enable or disable the /api/doc live spec route.
        'enabled' => true,

        // Route path relative to the global prefix.
        // prefix='api', path='doc' → /api/doc
        'path' => 'doc',

        // Middleware for the doc route.
        'middleware' => ['api'],

        // Info block for the generated spec.
        'info' => [
            'title'       => env('APP_NAME', 'API') . ' Documentation',
            'description' => 'Auto-generated API documentation.',
            'version'     => '1.0.0',
        ],

        // Server URLs for the spec.
        // Empty array → uses APP_URL automatically.
        'servers' => [],

        // Security scheme applied to routes with auth middleware.
        // Supported: 'bearer', 'basic', 'apiKey', null (no security)
        'default_security' => 'bearer',

        // Output path for `php artisan apilot:generate-spec`.
        'export_path' => storage_path('app/openapi.json'),
    ],

];
```

## Common Customizations

### API versioning

```php
'prefix' => 'api/v1',
```

Routes are now at `/api/v1/posts`, `/api/v1/comments`, etc.

### Larger default page size

```php
'pagination' => [
    'default_per_page' => 25,
    'max_per_page' => 200,
    'per_page_param' => 'per_page',
],
```

### Require auth for the doc route

```php
'openapi' => [
    'enabled' => true,
    'path' => 'doc',
    'middleware' => ['api', 'auth:sanctum'],
    // ...
],
```

### Disable the doc route in production

```php
'openapi' => [
    'enabled' => env('APILOT_DOC_ENABLED', true),
    // ...
],
```

```dotenv
# .env.production
APILOT_DOC_ENABLED=false
```

### Custom sort parameter name

```php
'sorting' => [
    'param' => 'order_by',  // clients use ?order_by=title instead of ?sort=title
],
```

---

**Next:** [Testing](14-testing.md)
