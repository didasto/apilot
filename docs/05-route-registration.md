# Route Registration

Apilot registers routes via `CrudRouteRegistrar`. A single call can register up to five REST endpoints, with full support for `only`, `except`, and middleware configuration.

## Basic Registration

```php
use Didasto\Apilot\Routing\CrudRouteRegistrar;
use App\Http\Controllers\Api\PostController;

CrudRouteRegistrar::resource('posts', PostController::class);
```

## Generated Routes

| Action | Method | URI | Controller Method |
|--------|--------|-----|-------------------|
| index | GET | `/api/posts` | `PostController::index` |
| show | GET | `/api/posts/{id}` | `PostController::show` |
| store | POST | `/api/posts` | `PostController::store` |
| update | PUT | `/api/posts/{id}` | `PostController::update` |
| destroy | DELETE | `/api/posts/{id}` | `PostController::destroy` |

The `api` prefix comes from the `prefix` config key. See [Configuration](13-configuration.md).

## Method Chaining

### `only()`

Limit to specific actions:

```php
// Read-only API — no write endpoints
CrudRouteRegistrar::resource('products', ProductController::class)
    ->only(['index', 'show']);

// Create and read, but no update or delete
CrudRouteRegistrar::resource('registrations', RegistrationController::class)
    ->only(['index', 'show', 'store']);
```

### `except()`

Register all actions except the specified ones:

```php
// Everything except delete
CrudRouteRegistrar::resource('comments', CommentController::class)
    ->except(['destroy']);

// No write endpoints
CrudRouteRegistrar::resource('reports', ReportController::class)
    ->except(['store', 'update', 'destroy']);
```

### `middleware()`

Attach middleware to the resource routes:

```php
// Authentication required for all actions
CrudRouteRegistrar::resource('posts', PostController::class)
    ->middleware(['auth:sanctum']);
```

Middleware specified here is **merged** with the global middleware from `config/apilot.php`. It does not replace it.

### Combining All Three

```php
// Authenticated read-only API
CrudRouteRegistrar::resource('invoices', InvoiceController::class)
    ->only(['index', 'show'])
    ->middleware(['auth:sanctum', 'apilot.json']);
```

## Global Prefix and Middleware

The route prefix and base middleware are controlled by config:

```php
// config/apilot.php
'prefix'     => 'api',
'middleware' => ['api'],
```

Per-route middleware (set via `->middleware(...)`) is merged on top of the global middleware list. Example: if global middleware is `['api']` and you add `->middleware(['auth:sanctum'])`, the effective middleware stack is `['api', 'auth:sanctum']`.

## Multiple Resources

```php
// routes/api.php

use Didasto\Apilot\Routing\CrudRouteRegistrar;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\ProductController;

// Full CRUD, authenticated
CrudRouteRegistrar::resource('posts', PostController::class)
    ->middleware(['auth:sanctum']);

// All actions, no auth required
CrudRouteRegistrar::resource('tags', TagController::class);

// Only read endpoints, authenticated
CrudRouteRegistrar::resource('products', ProductController::class)
    ->only(['index', 'show'])
    ->middleware(['auth:sanctum']);

// No delete, authenticated
CrudRouteRegistrar::resource('comments', CommentController::class)
    ->except(['destroy'])
    ->middleware(['auth:sanctum']);
```

## Works with Both Controller Types

`CrudRouteRegistrar` works identically for `ModelCrudController` and `ServiceCrudController`. The registrar is agnostic about the controller's internals.

---

**Next:** [Filtering](06-filtering.md)
