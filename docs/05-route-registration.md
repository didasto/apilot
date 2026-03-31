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

## Route Attributes (`#[ApiResource]`)

As an alternative to `CrudRouteRegistrar`, routes can be declared directly on the controller class using the `#[ApiResource]` PHP attribute. Both approaches work in parallel and can be mixed freely.

### Attribute Reference

```php
#[Attribute(Attribute::TARGET_CLASS)]
final class ApiResource
{
    public function __construct(
        public readonly string $path,              // URI path, e.g. '/posts' or '/api/v1/posts'
        public readonly ?array $only = null,       // Limit to these actions
        public readonly ?array $except = null,     // Exclude these actions
        public readonly ?string $name = null,      // Route name prefix (auto-derived from path if null)
        public readonly array $middleware = [],    // Additional middleware
    ) {}
}
```

### Basic Usage

```php
use Didasto\Apilot\Attributes\ApiResource;
use Didasto\Apilot\Controllers\ModelCrudController;
use App\Models\Post;

// All 5 CRUD routes, auto-generated route name prefix 'posts'
#[ApiResource(path: '/posts')]
class PostController extends ModelCrudController
{
    protected string $model = Post::class;
}
```

### Only / Except

```php
// Read-only: GET /api/posts and GET /api/posts/{id}
#[ApiResource(path: '/posts', only: ['index', 'show'])]
class PostController extends ModelCrudController { ... }

// No delete: all routes except DELETE
#[ApiResource(path: '/posts', except: ['destroy'])]
class PostController extends ModelCrudController { ... }
```

### Middleware

```php
#[ApiResource(path: '/posts', middleware: ['auth:sanctum'])]
class PostController extends ModelCrudController { ... }
```

Per-attribute middleware is **merged** with the global `config('apilot.middleware')` list.

### Custom Route Name Prefix

```php
// Route names: api.v1.posts.index, api.v1.posts.show, ...
#[ApiResource(path: '/posts', name: 'api.v1.posts')]
class PostController extends ModelCrudController { ... }
```

Without `name`, the route name prefix is derived from the last path segment (`'/posts'` → `'posts'`).

### Custom Path Prefix

When the path contains more than one segment, everything before the last segment becomes the route prefix:

```php
// Routes: GET /api/v2/posts, GET /api/v2/posts/{id}, ...
#[ApiResource(path: '/api/v2/posts')]
class PostController extends ModelCrudController { ... }
```

For a single-segment path (`'/posts'`), the global `config('apilot.prefix')` is used as the prefix.

### Works with ServiceCrudController

```php
#[ApiResource(path: '/products', only: ['index', 'show'])]
class ExternalProductController extends ServiceCrudController
{
    protected string $serviceClass = ExternalProductService::class;
}
```

### Registering Attribute-Annotated Controllers

**Option 1 — Explicit list** (e.g. in a service provider or `routes/api.php`):

```php
use Didasto\Apilot\Routing\AttributeRouteRegistrar;

app(AttributeRouteRegistrar::class)->register([
    \App\Http\Controllers\Api\PostController::class,
    \App\Http\Controllers\Api\TagController::class,
]);
```

**Option 2 — Directory scan:**

```php
app(AttributeRouteRegistrar::class)->registerDirectory(
    app_path('Http/Controllers/Api'),
    'App\\Http\\Controllers\\Api',
);
```

Only classes with `#[ApiResource]` are registered; all other PHP files in the directory are ignored.

**Option 3 — Auto-Discovery via config:**

```php
// config/apilot.php
'auto_discover' => [
    'enabled' => true,
    'directories' => [
        [
            'directory' => app_path('Http/Controllers/Api'),
            'namespace' => 'App\\Http\\Controllers\\Api',
        ],
    ],
],
```

When `enabled` is `true`, the service provider scans the configured directories automatically on boot.

### CrudRouteRegistrar vs. #[ApiResource]

| Aspect | `CrudRouteRegistrar` | `#[ApiResource]` |
|--------|----------------------|-----------------|
| Declaration location | `routes/api.php` or service provider | On the controller class itself |
| Auto-discovery | Not supported | Supported via `registerDirectory` |
| OpenAPI integration | Yes | Yes (same `RouteRegistry`) |
| Parallel use | Yes | Yes |

Both register routes in the same `RouteRegistry`, so OpenAPI generation works identically for both.

---

**Next:** [Filtering](06-filtering.md)
