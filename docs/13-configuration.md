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
    |
    | Controls how Apilot formats JSON responses. Three modes are available:
    |
    | null     → Laravel Default. Apilot does not intervene.
    |            Laravel's JsonResource determines the format (default: "data" wrapper).
    |
    | []       → No wrapper. Single items are returned as a direct JSON object.
    |            Paginated responses use "items" as the collection key.
    |
    | 'string' → Named wrapper. The given string is used as the wrapper key.
    |            Examples: 'data', 'result', 'payload'
    |
    | Error responses (404, 403, 422) are never affected by this setting.
    */
    'response_wrapper' => null,

    /*
    |--------------------------------------------------------------------------
    | Auto-Discovery for #[ApiResource] Attributes
    |--------------------------------------------------------------------------
    | When enabled, the service provider scans the configured directories
    | for controller classes annotated with #[ApiResource] and registers
    | their routes automatically on boot.
    */
    'auto_discover' => [

        // Set to true to enable automatic scanning.
        'enabled' => false,

        // Directories to scan. Each entry needs 'directory' and 'namespace'.
        'directories' => [
            [
                'directory' => null, // e.g. app_path('Http/Controllers/Api')
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

## Response Wrapper Modes

The `response_wrapper` option controls how Apilot wraps your JSON responses. Three modes are available.

### Mode 1: `null` — Laravel Default

```php
'response_wrapper' => null,
```

Apilot does not intervene in response formatting. Laravel's `JsonResource` determines the format. By default Laravel adds a `"data"` wrapper unless you have called `JsonResource::withoutWrapping()` in your application.

**Single-item (show, store, update):**
```json
{ "data": { "id": 1, "title": "Post 1" } }
```

**Collection (index):**
```json
{
    "data": [
        { "id": 1, "title": "Post 1" },
        { "id": 2, "title": "Post 2" }
    ],
    "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
    "meta": { "current_page": 1, "last_page": 3, "per_page": 15, "total": 7 }
}
```

---

### Mode 2: `[]` — No Wrapper

```php
'response_wrapper' => [],
```

Single items are returned as a direct JSON object. Paginated responses use `"items"` as the collection key (required to transport `meta` and `links`).

**Single-item (show, store, update):**
```json
{ "id": 1, "title": "Post 1", "created_at": "2026-03-31T22:18:02.000000Z" }
```

**Collection (index):**
```json
{
    "items": [
        { "id": 1, "title": "Post 1" },
        { "id": 2, "title": "Post 2" }
    ],
    "meta": { "current_page": 1, "last_page": 3, "per_page": 15, "total": 7 },
    "links": { "first": "...", "last": "...", "prev": null, "next": "..." }
}
```

---

### Mode 3: `'string'` — Named Wrapper

```php
'response_wrapper' => 'data',   // or 'result', 'payload', etc.
```

All responses are wrapped under the specified key.

**Single-item with `'result'`:**
```json
{ "result": { "id": 1, "title": "Post 1" } }
```

**Collection with `'result'`:**
```json
{
    "result": [{ "id": 1, "title": "Post 1" }],
    "meta": { "current_page": 1, "last_page": 3, "per_page": 15, "total": 7 },
    "links": { "first": "...", "last": "...", "prev": null, "next": "..." }
}
```

---

### Destroy and Error Responses

`DELETE` always returns `204 No Content` with an empty body — unaffected by the wrapper.

Error responses (`404`, `403`, `422`, `501`) have their own fixed format and are never wrapped:

```json
{ "error": { "message": "Resource not found.", "status": 404 } }
```

---

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
